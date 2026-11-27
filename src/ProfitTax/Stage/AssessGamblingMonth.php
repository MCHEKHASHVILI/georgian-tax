<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Stage;

use Closure;
use GeorgianTax\Domain\Event\CloseGamblingMonth;
use GeorgianTax\Domain\Event\GamblingActivity;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Engine\Deadline;
use GeorgianTax\ProfitTax\Outflow;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;
use OutOfBoundsException;

/**
 * A gambling organizer pays profit tax on the month of its gambling activity, not on what it pays
 * out (97.1 sets 97.9–9³ aside):
 *
 *   - an online totalizator on the month's stakes, at 7% (97.9, 309.16);
 *   - its part from foreign citizens on stakes less winnings paid to them, at 5% (97.9³, 98.5);
 *   - a gaming hall or online gambling on stakes less winnings paid, at 20% (97.9¹, 98.6);
 *   - online gambling's part from foreign citizens on the same object, at 5% (97.9¹, 98.5).
 *
 * None of these is grossed up — 97.10 grosses up the objects of 97.1–3 only. The reporting period is
 * the calendar month (135.3–4), declared and paid by the 15th of the next (153.10, 309.16).
 *
 * A month whose winnings paid exceed its stakes has no object; the Code gives no carry-forward of
 * that excess (121's losses are of gross income less deductions), so none is noted.
 *
 * The payers: 153.10 names resident companies, organizations and Georgian PEs for 97.9¹ and 9³; an
 * individual organizer pays income tax instead (80.7, 80.7¹, 309.16), which is not modelled. How a
 * non-resident company without a Georgian PE would declare it the Code does not say: Unresolved.
 *
 * Not modelled: deeming a dividend paid from this profit first (97.9²) — tag the dividend with
 * `ExemptProfit::GamblingClubOrTotalizator` (99.1.ს) where that exemption reaches it; and 97.1's other
 * objects outside the gambling part of a company that also does other business.
 */
#[Article('97.9–9³', 'Profit tax object of gambling organizers')]
final class AssessGamblingMonth implements Stage
{
    public function handle(Outflow $o, Closure $next): Outcome
    {
        $event = $o->event;

        if (!$event instanceof CloseGamblingMonth) {
            return $next($o);
        }

        $organizer = $event->organizer;
        [$object, $rateArticle, $rateName] = $this->law($event);
        $objectCite = new Citation($object);

        if (!in_array($organizer->kind, [PartyKind::Company, PartyKind::Organization], true)) {
            return $o->notTaxable(new Citation('96'), "An individual organizer pays income tax on it (80.7, 80.7¹, 309.16), not profit tax.");
        }

        if (!$organizer->isResident() && !$organizer->hasGeorgianPE) {
            return $o->unresolved($objectCite, 'The monthly declaration (153.10) names resident companies, organizations and Georgian PEs; the Code does not say how a non-resident without a Georgian PE declares this object.');
        }

        $monthEnd = $event->date();
        $period = $monthEnd->format('Y-m');
        $cite = new Citation($rateArticle, $rateName);

        try {
            $rate = $o->rates->rate($rateArticle, $monthEnd);
        } catch (OutOfBoundsException) {
            return $o->unresolved($cite, "No rate $rateArticle is in force on {$monthEnd->format('Y-m-d')}.");
        }

        $players = $event->foreignPlayers ? ' from players who are foreign citizens' : '';

        if ($event->activity === GamblingActivity::OnlineTotalizator && !$event->foreignPlayers) {
            $base = $event->stakes;
            $notes = ["Stakes received in $period: $base, taxed whole; winnings are not deducted (309.16)."];
        } else {
            $base = $event->stakes->minus($event->paidOut());
            $notes = ["Stakes received$players in $period {$event->stakes} less winnings paid {$event->paidOut()} = $base ($object)."];
        }

        if (!$base->isPositive()) {
            return $o->notTaxable($objectCite, implode(' ', [...$notes, "Nothing to tax for $period."]));
        }

        $due = Deadline::of(Collection::MonthlyDeclaration, $monthEnd);

        return new Taxable(
            Outflow::TAX, $cite, $organizer, $base, $rate, $base->percent($rate->percent),
            Collection::MonthlyDeclaration, null, $due, $notes, countsAsTurnover: false,
        );
    }

    /** @return array{string, string, string} the object's article, the rate's article and its name */
    private function law(CloseGamblingMonth $event): array
    {
        return match (true) {
            $event->activity === GamblingActivity::OnlineTotalizator && !$event->foreignPlayers
                => ['97.9', '309.16', 'Online totalizator: stakes'],
            $event->activity === GamblingActivity::OnlineTotalizator
                => ['97.9³', '98.5', 'Online gambling and totalizators: foreign players'],
            $event->foreignPlayers
                => ['97.9¹', '98.5', 'Online gambling and totalizators: foreign players'],
            default
                => ['97.9¹', '98.6', 'Gaming halls and online gambling'],
        };
    }
}
