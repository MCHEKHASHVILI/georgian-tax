<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax\Stage;

use Closure;
use GeorgianTax\Domain\Event\PayPropertyInstalment;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\PropertyTax\Holding;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;
use Mchekhashvili\TaxLegislation\Rates\RateTable;
use OutOfBoundsException;

/**
 * A company's or organization's current payment of property tax (205.3): last year's annual tax on
 * its property, by 15 June of the tax year, as a `Levied` of one current payment. It is paid on
 * account of the year's tax, declared by 1 April of the next year (205.2), and is not added to it.
 *
 *   205.9  founded after the year began: no current payment for the year
 *   205.4  the year's expected tax at least 50% (the rate `205.4`, read on 15 June) below last
 *          year's, and the tax authority told by 1 June: the amount the company chose, or nothing
 *   205.6  given last year's property at this year's rates: that amount
 *   205.3  otherwise: last year's tax
 *
 * Individuals make no current payment: they pay the year's tax by 15 November (205.14). Nor does
 * the state, which pays no property tax on its own property.
 *
 * Not modelled: 205.5's late-payment interest when the year's actual tax does not bear out the 50%
 * reduction (from 15 June to the declaration's day, under the Code's interest rules), and 205.15's
 * procedure, which is the Minister's.
 */
#[Article('205.3', 'Current payment of property tax')]
final class CurrentPayment implements Stage
{
    public function __construct(private readonly RateTable $rates) {}

    public function handle(Holding $h, Closure $next): Outcome
    {
        $event = $h->event;

        if (!$event instanceof PayPropertyInstalment) {
            return $next($h);
        }

        $company = $event->company;

        if ($company->kind === PartyKind::State) {
            return $h->notTaxable(new Citation('205.3'), 'The state does not pay property tax on its own property.');
        }

        if ($company->isIndividual()) {
            return $h->notTaxable(new Citation('205.3'), 'Only a company or organization makes a current payment; an individual pays the year\'s tax by 15 November (205.14).');
        }

        if ($event->foundedInYear()) {
            return $h->notTaxable(new Citation('205.9'), "Founded on {$event->foundedOn->format('Y-m-d')}, after {$event->year} began: no current payment for {$event->year}. Next year's is {$event->year}'s annual tax in full.");
        }

        $last = $event->lastYearTax;
        $notes = ["Last year's annual property tax: $last (205.3)."];

        if ($event->expectedTax !== null) {
            $reduction = $this->reduction($event, $notes);

            if ($reduction !== null) {
                return $reduction->isZero()
                    ? $h->notTaxable(new Citation('205.4'), implode(' ', [...$notes, "Nothing paid for {$event->year}."]))
                    : $this->levied($event, new Citation('205.4', 'Reduced current payment'), $reduction, $notes);
            }
        }

        if ($event->atCurrentRates !== null) {
            $notes[] = "Paid at {$event->year}'s rates: {$event->atCurrentRates} (205.6).";

            return $this->levied($event, new Citation('205.6', 'Current payment at the year\'s rate'), $event->atCurrentRates, $notes);
        }

        if ($last->isZero()) {
            return $h->notTaxable(new Citation('205.3'), "No property tax last year, so no current payment for {$event->year}.");
        }

        return $this->levied($event, new Citation('205.3', 'Current payment of property tax'), $last, $notes);
    }

    /**
     * 205.4 — the amount to pay when the reduction is allowed, or null (with the reason in the notes)
     * when it is not.
     *
     * @param list<string> $notes
     */
    private function reduction(PayPropertyInstalment $event, array &$notes): ?Money
    {
        $last = $event->lastYearTax;
        $expected = $event->expectedTax;

        try {
            $share = $this->rates->rate('205.4', $event->date())->percent;
        } catch (OutOfBoundsException) {
            $notes[] = "No rate 205.4 is in force on {$event->date()->format('Y-m-d')}, so the payment is not reduced.";

            return null;
        }

        $fall = $last->minus($expected);

        if (!$last->isPositive() || $fall->lessThan($last->percent($share))) {
            $notes[] = "The expected tax of $expected is not at least $share% below last year's: not reduced (205.4).";

            return null;
        }

        $by = $event->noticeBy();

        if ($event->notifiedOn === null || $event->notifiedOn > $by) {
            $when = $event->notifiedOn === null ? 'was not told' : "was told on {$event->notifiedOn->format('Y-m-d')}";
            $notes[] = "The expected tax of $expected is $share% or more below last year's, but the tax authority $when, not by {$by->format('Y-m-d')}: not reduced (205.4).";

            return null;
        }

        $pay = $event->reducedTo ?? Money::zero($last->currency);
        $notes[] = "Expected tax $expected, at least $share% below last year's, told on {$event->notifiedOn->format('Y-m-d')}: the payment is reduced to $pay (205.4).";

        return $pay;
    }

    /** @param list<string> $notes */
    private function levied(PayPropertyInstalment $event, Citation $citation, Money $amount, array $notes): Levied
    {
        return new Levied(
            Holding::TAX, $citation, $event->company, 1, 'current payment', $amount, $amount,
            Collection::AnnualDeclaration, $event->date(),
            [...$notes, "Paid on account of {$event->year}'s tax, declared by 1 April " . ($event->year + 1) . ' (205.2).'],
        );
    }
}
