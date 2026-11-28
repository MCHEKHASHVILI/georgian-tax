<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Stage;

use Closure;
use GeorgianTax\Domain\Event\CloseRentalYear;
use GeorgianTax\Domain\Party\PartyKind;
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
 * A non-resident company paid rent by someone who is not a tax agent pays profit tax on the year:
 * on its gross income from a Georgian source less the deductions connected with earning it (97.11),
 * at the general rate (98.1) — 97.4 sets that rent aside from withholding under 134. The profit is
 * not grossed up: 97.10 grosses up the objects of 97.1–3 only.
 *
 * Earlier years' losses still carried are set against the profit (121.4, a legal person's); a year
 * whose deductions exceed its gross income adds the excess to them, and is not taxed. The tax is
 * declared, and paid, for the calendar year by 1 April of the next (153.1.გ, 62.2).
 *
 * The rate is the rate `98.1` in force on 31 December of the year; none in force is Unresolved.
 * 97.11 names a non-resident enterprise (საწარმო): a resident company or a Georgian PE is taxed on
 * what it pays out (97.1, 97.3), a non-resident individual's rent is income tax (134.1.დ¹, 80.6), and a
 * non-resident organization's stays under 134.1.ე.
 *
 * Not modelled: computing gross income and deductions, which years' losses are still open (121.4,
 * 122), and when 97.11 began (the Code's current text does not say; the rate is read from 98.1's history).
 */
#[Article('97.11', "Profit tax object of a non-resident's rent from non-agents")]
final class AssessRentalYear implements Stage
{
    public function handle(Outflow $o, Closure $next): Outcome
    {
        $event = $o->event;

        if (!$event instanceof CloseRentalYear) {
            return $next($o);
        }

        $company = $event->company;
        $object = new Citation('97.11', "Non-resident's rent from non-agents");

        if ($company->kind !== PartyKind::Company) {
            return $o->notTaxable($object, $company->kind->isIndividual()
                ? "97.11 names a non-resident enterprise; a non-resident individual's rent is income tax (134.1.დ¹, 80.6)."
                : '97.11 names a non-resident enterprise; an organization is not one.');
        }

        if (!$company->isNonResidentWithoutPE()) {
            return $o->notTaxable($object, 'A resident company or a Georgian PE is taxed on what it pays out (97.1, 97.3), not on the rent it receives.');
        }

        $cite = new Citation('98.1', 'Profit tax rate');
        $yearEnd = $event->date();

        try {
            $rate = $o->rates->rate('98.1', $yearEnd);
        } catch (OutOfBoundsException) {
            return $o->unresolved($cite, "No rate 98.1 is in force on {$yearEnd->format('Y-m-d')}.");
        }

        $result = $event->grossIncome->minus($event->deductions);
        $losses = $event->losses();
        $notes = ["Gross income from a Georgian source {$event->grossIncome} less deductions {$event->deductions} = $result (97.11)."];

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

        $notes[] = 'Not grossed up: 97.10 is for 97.1–3. Declared by 1 April of the next year (153.1.გ).';

        return new Taxable(
            Outflow::TAX, $cite, $company, $profit, $rate, $profit->percent($rate->percent),
            Collection::AnnualDeclaration, null, Deadline::of(Collection::AnnualDeclaration, $yearEnd), $notes, countsAsTurnover: false,
        );
    }
}
