<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Rule;

use GeorgianTax\Domain\Event\ExpenseNature;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\ProfitTax\Outflow;
use GeorgianTax\ProfitTax\OutflowKind;

/**
 * Representation costs (Art. 8.33) are free up to 1% of last year's revenue — or of last year's
 * costs, if those exceeded revenue (98⁴.2). Only the part of this expense above the yearly limit
 * is taxed. In the founding year the limit is 1% of the year's own costs (98⁴.3), known only at
 * year end. The shares are rates (98⁴.2, 98⁴.3 in resources/law/rates.php or the database).
 *
 * The limit is 1% of the larger of last year's revenue and costs, so one of them is enough while
 * the year's representation stays within 1% of it; beyond that, the other is needed to decide.
 */
#[Article('98⁴', 'Representation costs over the limit')]
final class RepresentationOverLimit implements Rule
{
    public function assess(Outflow $o): ?Outcome
    {
        if ($o->kind !== OutflowKind::Representation) {
            return null;
        }

        if ($o->books->foundedIn($o->company) === $o->year()) {
            return $o->unresolved($this, "In the founding year the limit is {$o->rate('98⁴.3')} of the year's costs (98⁴.3); assess at year end.");
        }

        $share = $o->rate('98⁴.2');
        $revenue = $o->books->revenue($o->company, $o->year() - 1);
        $costs = $o->books->costs($o->company, $o->year() - 1);

        if ($revenue === null && $costs === null) {
            return $o->unresolved($this, "The limit is $share of last year's revenue, or of its costs if they exceeded it, which the books don't give.");
        }

        $byCosts = $revenue === null || ($costs !== null && $costs->greaterThan($revenue));
        $limit = ($byCosts ? $costs : $revenue)->percent($share->percent);
        $measure = $byCosts ? "last year's costs" : "last year's revenue";
        $before = $o->ledger->spent($o->company, $o->year(), ExpenseNature::Representation->value);
        $after = $before->plus($o->amount);

        if ($after->lessThanOrEqual($limit)) {
            return $o->notTaxable($this, "Within this year's representation limit of $limit ($share of $measure).");
        }

        if ($revenue === null) {
            return $o->unresolved($this, "Over $limit, $share of last year's costs; the limit is $share of last year's revenue if the costs did not exceed it, which the books don't give.");
        }

        if ($costs === null) {
            return $o->unresolved($this, "Over $limit, $share of last year's revenue; if last year's costs exceeded it, the limit is $share of the costs, which the books don't give.");
        }

        $excess = Money::min($o->amount, $after->minus($limit));

        return $o->taxed($this, '98.1', $excess, ["Only the $excess above this year's limit of $limit ($share of $measure) is taxed."]);
    }
}
