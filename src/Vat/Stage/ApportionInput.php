<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Stage;

use Closure;
use DateTimeImmutable;
use GeorgianTax\Domain\Event\ApportionInputVat;
use GeorgianTax\Domain\Event\CloseFixedAssetYear;
use GeorgianTax\Domain\Event\CorrectApportionment;
use GeorgianTax\Domain\Event\CreditFixedAsset;
use GeorgianTax\Domain\Event\FixedAsset;
use GeorgianTax\Domain\Event\SelfSupplyReason;
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
 * A fixed asset used for both kinds of operation, where they cannot be told apart, goes by 177.5–6
 * instead. When last year's operations without the right to credit were under 20% of the total
 * turnover (the rate `177.5.ა`, read on the first reporting period's last day), its VAT is credited
 * in full in its first reporting period, and each year end gives back a tenth (real estate) or a
 * fifth (other fixed assets) of it in that year's share of operations without the right to credit
 * (177.5.ა, 177.6). Otherwise nothing is credited at first, and each year end credits a tenth or a
 * fifth in the year's share with the right to credit (177.5.ბ, 177.6). The years run from the one
 * the asset was put into use: 10 or 5 of them. A building the person built and put into use, or its
 * own building's repair, whose VAT it was charged as a self-supply (160.3.ბ, 160¹.2.ბ), goes the
 * same way as if bought from another person (177.8).
 *
 * In a year whose operations without the right to credit were under 5% of the turnover, 177.7 sets
 * 177.1–6 aside and gives the full credit. For a fixed asset this takes the year's tenth or fifth
 * in full — nothing given back under 177.5.ა, all of it credited under 177.5.ბ. That reading is
 * provisional: the owner has been asked (ROADMAP, 2026-11-18) whether 177.7 should instead credit
 * all the VAT not yet credited.
 *
 * Not modelled: the credit on registration, deregistration or the supply of a fixed asset (177.10,
 * on the Minister's terms).
 */
#[Article('177', 'Proportional VAT credit')]
final class ApportionInput implements Stage
{
    public function handle(Supply $s, Closure $next): Outcome
    {
        $event = $s->event;

        if (!$event instanceof ApportionInputVat && !$event instanceof CorrectApportionment
            && !$event instanceof CreditFixedAsset && !$event instanceof CloseFixedAssetYear) {
            return $next($s);
        }

        if (!$event->taxpayer->is(Status::VatPayer)) {
            return $s->notTaxable(new Citation('174.3'), 'Only a person registered as a VAT payer may credit input VAT.');
        }

        return match (true) {
            $event instanceof ApportionInputVat => $this->month($s, $event),
            $event instanceof CorrectApportionment => $this->year($s, $event),
            $event instanceof CreditFixedAsset => $this->fixedAsset($s, $event),
            default => $this->fixedAssetYear($s, $event),
        };
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
        $deMinimis = $this->rate($s, '177.7', $event->date());

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

    /** The first reporting period of a fixed asset in mixed use: all of its VAT, or none yet (177.5). */
    private function fixedAsset(Supply $s, CreditFixedAsset $event): Outcome
    {
        $asset = $event->asset;
        $limit = $this->rate($s, '177.5.ა', $event->date());
        $prior = $event->priorYear;
        $last = $event->year - 1;

        $under = $limit !== null && $prior !== null && $prior->total()->isPositive()
            && $prior->nonCreditable->lessThan($prior->total()->percent($limit));

        $notes = [...$this->assetNotes($asset), match (true) {
            $prior === null || !$prior->total()->isPositive() => "No turnover in $last to test against 177.5.ა.",
            default => "In $last operations without the right to credit came to {$prior->nonCreditable} of a total turnover of {$prior->total()}, "
                . ($under ? 'under' : 'not under') . " $limit% (177.5.ა).",
        }];

        if ($under) {
            return $this->credit($event->taxpayer, '177.5.ა', $event->date(), 1.0, $asset->vat->negated(), $asset->vat->negated(), [
                ...$notes,
                "The whole VAT of {$asset->vat} is credited in the first reporting period (177.5.ა).",
                "Each year end from {$asset->inUseFrom} to {$asset->lastYear()} gives back a "
                    . ($asset->realEstate ? 'tenth' : 'fifth') . " of it in that year's share of operations without the right to credit ({$asset->spreadRef()}).",
            ]);
        }

        return $this->credit($event->taxpayer, '177.5.ბ', $event->date(), 0.0, $asset->vat->negated(), Money::GEL(0), [
            ...$notes,
            'Nothing is credited now: the VAT is credited only in the last reporting period of each calendar year (177.5.ბ),',
            "a " . ($asset->realEstate ? 'tenth' : 'fifth') . " of it a year from {$asset->inUseFrom} to {$asset->lastYear()}, in the year's share of operations with the right to credit ({$asset->spreadRef()}).",
        ]);
    }

    /** A calendar year's end for a fixed asset in mixed use: its tenth or fifth given back or credited (177.5–6). */
    private function fixedAssetYear(Supply $s, CloseFixedAssetYear $event): Outcome
    {
        $asset = $event->asset;
        $span = "{$asset->years()} calendar years from the year it was put into use, {$asset->inUseFrom}–{$asset->lastYear()}";

        if ($event->year < $asset->inUseFrom || $event->year > $asset->lastYear()) {
            return $s->notTaxable(new Citation($asset->spreadRef()), "{$event->year} is outside the $span: nothing is "
                . ($event->creditedInFull ? 'given back' : 'credited') . ' for it.');
        }

        $share = $event->turnover->share();

        if ($share === null) {
            return $s->unresolved(new Citation('177.5'), "No turnover in {$event->year} to divide the year's part by.");
        }

        $part = $asset->yearsPart();
        $total = $event->turnover->total();
        $nonCreditable = $event->turnover->nonCreditable;
        $deMinimis = $this->rate($s, '177.7', $event->date());
        $fraction = $asset->realEstate ? 'a tenth' : 'a fifth';

        $notes = [...$this->assetNotes($asset), "{$event->year}'s part: $fraction of {$asset->vat}, $part ({$asset->spreadRef()}, the $span)."];

        if ($deMinimis !== null && $nonCreditable->lessThan($total->percent($deMinimis))) {
            $notes[] = "Operations without the right to credit, $nonCreditable, are under $deMinimis% of the turnover of $total: full credit (177.7).";

            return $event->creditedInFull
                ? $this->credit($event->taxpayer, '177.7', $event->date(), 0.0, $part, Money::GEL(0), [...$notes, 'Nothing of the year\'s part is given back.'])
                : $this->credit($event->taxpayer, '177.7', $event->date(), 1.0, $part->negated(), $part->negated(), [...$notes, "The year's part is credited in full: $part."]);
        }

        if ($event->creditedInFull) {
            $back = $part->multipliedBy(1 - $share);

            return $this->credit($event->taxpayer, '177.5.ა', $event->date(), 1 - $share, $part, $back, [
                ...$notes,
                "Credited in full in its first reporting period; given back in {$event->year}'s share of operations without the right to credit, "
                    . Turnover::percent(1 - $share) . " ($nonCreditable of $total): $back (177.5.ა).",
            ]);
        }

        $credit = $part->multipliedBy($share);

        return $this->credit($event->taxpayer, '177.5.ბ', $event->date(), $share, $part->negated(), $credit->negated(), [
            ...$notes,
            "Credited in {$event->year}'s share of operations with the right to credit, " . Turnover::percent($share) . " ({$event->turnover}): $credit (177.5.ბ).",
        ]);
    }

    /** @return list<string> */
    private function assetNotes(FixedAsset $asset): array
    {
        $notes = ["A fixed asset used for operations with and without the right to credit that cannot be told apart: $asset (177.5)."];

        if ($asset->selfSupply !== null) {
            $notes[] = "Its VAT was charged on a supply to itself ({$asset->selfSupply->value}), and goes as it would on "
                . ($asset->selfSupply === SelfSupplyReason::OwnBuildingPutIntoUse ? 'the building' : 'the repair')
                . ' bought from another person (177.8).';
        }

        return $notes;
    }

    private function rate(Supply $s, string $key, DateTimeImmutable $on): ?float
    {
        try {
            return $s->rates->rate($key, $on)->percent;
        } catch (OutOfBoundsException) {
            return null;
        }
    }

    /** @param list<string> $notes */
    private function credit(Party $taxpayer, string $ref, DateTimeImmutable $on, float $quantity, Money $perUnit, Money $amount, array $notes): Levied
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
