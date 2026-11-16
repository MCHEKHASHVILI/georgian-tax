<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Stage;

use Closure;
use GeorgianTax\Domain\Event\ApportionInputVat;
use GeorgianTax\Domain\Event\CorrectApportionment;
use GeorgianTax\Domain\Event\Turnover;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Deadline;
use GeorgianTax\Vat\Supply;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;
use OutOfBoundsException;

/**
 * Input VAT on goods and services used both for operations with the right to credit and for
 * operations without it is credited only in the share that belongs to the first (177.1): the
 * turnover with the right to credit over all the turnover, without VAT, fixed as a percentage
 * (177.2).
 *
 * Each month takes last year's annual percentage, or the month's own when none was fixed last year
 * (177.4.ა–ბ); the year's last reporting period corrects the year to the year's percentage (177.4).
 * If the operations without the right to credit came to less than 5% of the turnover in the year,
 * 177.1–6 do not apply and the whole input VAT is credited (177.7) — a year-end test, as the Code
 * counts that turnover "during the calendar year", so the months are apportioned regardless.
 *
 * The outcome is a `Levied` for the VAT payer in the month's declaration (due the 15th of the next
 * month): negative for a credit, positive for credit given back. Only a registered VAT payer credits
 * at all (174.3). The percentage is not rounded: the Code fixes it as a percentage without saying
 * how, so the notes show it to two decimals. The 5% is the rate `177.7`, read on 31 December.
 *
 * Not modelled: fixed assets used for both kinds of operation (177.5–6, 177.8), and the credit on
 * registration, deregistration or the supply of a fixed asset (177.10, on the Minister's terms).
 */
#[Article('177', 'Proportional VAT credit')]
final class ApportionInput implements Stage
{
    public function handle(Supply $s, Closure $next): Outcome
    {
        $event = $s->event;

        if (!$event instanceof ApportionInputVat && !$event instanceof CorrectApportionment) {
            return $next($s);
        }

        if (!$event->taxpayer->is(Status::VatPayer)) {
            return $s->notTaxable(new Citation('174.3'), 'Only a person registered as a VAT payer may credit input VAT.');
        }

        return $event instanceof ApportionInputVat ? $this->month($s, $event) : $this->year($s, $event);
    }

    private function month(Supply $s, ApportionInputVat $event): Outcome
    {
        [$turnover, $ref, $which] = $event->priorYear?->share() !== null
            ? [$event->priorYear, '177.4.ა', ($event->year - 1) . "'s annual percentage"]
            : [$event->monthTurnover, '177.4.ბ', "the month's percentage"];

        $share = $turnover?->share();

        if ($share === null) {
            return $s->unresolved(new Citation('177.4.ბ'), 'No turnover to fix a percentage on: neither last year nor the month has any (177.2).');
        }

        $credit = $event->inputVat->multipliedBy($share);

        return $this->credit($event->taxpayer, $ref, $event->date(), $share, $event->inputVat->negated(), $credit->negated(), [
            "Input VAT of {$event->inputVat} on goods and services used for operations with and without the right to credit (177.1).",
            "Credited at $which, " . Turnover::percent($share) . " ($turnover, 177.2): $credit; not credited: {$event->inputVat->minus($credit)} (178.ბ).",
            "Provisional: corrected to {$event->year}'s annual percentage in the year's last reporting period (177.4).",
        ]);
    }

    private function year(Supply $s, CorrectApportionment $event): Outcome
    {
        try {
            $deMinimis = $s->rates->rate('177.7', $event->date())->percent;
        } catch (OutOfBoundsException) {
            $deMinimis = null;
        }

        $total = $event->turnover->total();
        $share = $event->turnover->share();

        if ($share === null) {
            return $s->unresolved(new Citation('177.2'), "No turnover in {$event->year} to fix the annual percentage on.");
        }

        $nonCreditable = $event->turnover->nonCreditable;

        if ($deMinimis !== null && $nonCreditable->lessThan($total->percent($deMinimis))) {
            $ref = '177.7';
            $share = 1.0;
            $how = "Operations without the right to credit, $nonCreditable, are under $deMinimis% of the turnover of $total: the whole input VAT is credited (177.7).";
        } else {
            $ref = '177.4';
            $how = "{$event->year}'s annual percentage: " . Turnover::percent($share) . " ({$event->turnover}, 177.2).";
        }

        $due = $event->inputVat->multipliedBy($share);
        $correction = $event->credited->minus($due);

        return $this->credit($event->taxpayer, $ref, $event->date(), 1.0, $correction, $correction, [
            "Input VAT of {$event->inputVat} on goods and services used for both kinds of operation in {$event->year} (177.1).",
            $how,
            "Creditable for the year: $due; credited during it: {$event->credited}; "
                . match (true) {
                    $correction->isNegative() => "{$correction->negated()} more is credited",
                    $correction->isPositive() => "$correction of credit is given back",
                    default => 'nothing to correct',
                } . " in the year's last reporting period (177.4).",
        ]);
    }

    /** @param list<string> $notes */
    private function credit(Party $taxpayer, string $ref, \DateTimeImmutable $on, float $quantity, Money $perUnit, Money $amount, array $notes): Levied
    {
        return new Levied(
            Supply::TAX,
            new Citation($ref, 'Proportional VAT credit'),
            $taxpayer,
            $quantity,
            'share of input VAT',
            $perUnit,
            $amount,
            Collection::MonthlyDeclaration,
            Deadline::of(Collection::MonthlyDeclaration, $on),
            $notes,
        );
    }
}
