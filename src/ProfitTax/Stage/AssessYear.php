<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Stage;

use Closure;
use GeorgianTax\Domain\Event\CloseYear;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Deadline;
use GeorgianTax\ProfitTax\Outflow;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;
use OutOfBoundsException;

/**
 * A bank, credit union, microfinance organization or lender pays profit tax on the year, not on
 * what it pays out: on the year's gross income less the Code's deductions (97.12), at 20% (98.4),
 * from 2023 (309.94). The profit is not grossed up — 97.10 grosses up the objects of 97.1–3 only.
 *
 * Earlier years' losses still carried are set against the profit (121.4); a year whose deductions
 * exceed its gross income adds that excess to the losses carried, and is not taxed. The tax is
 * declared, and paid, for the calendar year by 1 April of the next (153.1, 62.2); the current
 * payments made in the year are credited against it (155.6), which the notes show.
 *
 * The rate is the rate `98.4` in force on 31 December of the year. A year before 2023 is Unresolved:
 * its object was the same (309.95), but the rate then is not in the Code's current text, and the
 * law's first recorded value is open-ended in the database. A year with no rate in force is too.
 *
 * Not modelled: computing gross income and deductions, which years' losses are still open (121.4,
 * 122), the next year's current payments (155.1–5), a period ending with liquidation (153.3), and
 * insurers' years before 2024 (309.95¹).
 */
#[Article('97.12', 'Profit tax object of banks and lenders')]
final class AssessYear implements Stage
{
    /** 309.94 — taxed under 97.12 from 1 January 2023. */
    public const FROM = 2023;

    public function handle(Outflow $o, Closure $next): Outcome
    {
        $event = $o->event;

        if (!$event instanceof CloseYear) {
            return $next($o);
        }

        $company = $event->company;

        if (!in_array($company->kind, [PartyKind::Company, PartyKind::Organization], true) || !($company->isResident() || $company->hasGeorgianPE)) {
            return $o->notTaxable(new Citation('96'), 'Only a resident company or organization, or a Georgian PE, pays profit tax.');
        }

        if (!$company->is(Status::CreditInstitution)) {
            return $o->notTaxable(new Citation('97.1'), 'Only banks, credit unions, microfinance organizations and lenders are taxed on the year (97.12); others pay when profit is distributed.');
        }

        $cite = new Citation('98.4', 'Profit tax rate of banks and lenders');
        $yearEnd = $event->date();

        if ($event->year < self::FROM) {
            return $o->unresolved(new Citation('309.95'), "Before " . self::FROM . " a bank's or lender's year was taxed on the same object, at the rate in force then, which the Code's current text does not give (309.94–95).");
        }

        try {
            $rate = $o->rates->rate('98.4', $yearEnd);
        } catch (OutOfBoundsException) {
            return $o->unresolved($cite, "No rate 98.4 is in force on {$yearEnd->format('Y-m-d')}.");
        }

        $result = $event->grossIncome->minus($event->deductions);
        $losses = $event->losses();
        $notes = ["Gross income {$event->grossIncome} less deductions {$event->deductions} = $result (97.12)."];

        if (!$result->isPositive()) {
            $carried = $losses->plus($result->negated());

            return $o->notTaxable(new Citation('121.4', 'Loss carried forward'), implode(' ', [
                ...$notes,
                'No taxable profit for ' . $event->year . ($result->isNegative() ? "; the excess of {$result->negated()} is carried forward, for up to 5 years (10 if chosen under 122)." : '.'),
                "Losses carried into " . ($event->year + 1) . ": $carried.",
            ]));
        }

        $used = Money::min($losses, $result);
        $profit = $result->minus($used);

        if ($used->isPositive()) {
            $notes[] = "Losses carried from earlier years set against it: $used (121.4); {$losses->minus($used)} still carried.";
        }

        if ($profit->isZero()) {
            return $o->notTaxable(new Citation('121.4', 'Loss carried forward'), implode(' ', [...$notes, 'Nothing left to tax for ' . $event->year . '.']));
        }

        $tax = $profit->percent($rate->percent);
        $paid = $event->paid();
        $due = Deadline::of(Collection::AnnualDeclaration, $yearEnd);

        if ($paid->isPositive()) {
            $left = $tax->minus($paid);
            $notes[] = "Current payments of $paid credited (155.6): "
                . ($left->isNegative() ? "{$left->negated()} overpaid." : "$left left to pay by {$due->format('Y-m-d')}.");
        }

        return new Taxable(
            Outflow::TAX, $cite, $company, $profit, $rate, $tax,
            Collection::AnnualDeclaration, null, $due, $notes, countsAsTurnover: false,
        );
    }
}
