<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Rule;

use GeorgianTax\Domain\Event\ExpenseNature;
use GeorgianTax\Domain\Money;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Article;
use GeorgianTax\ProfitTax\Outflow;
use GeorgianTax\ProfitTax\OutflowKind;

/**
 * Representation costs (Art. 8.33) are free up to 1% of last year's revenue — or of last year's
 * costs, if those exceeded revenue (98⁴.2). Only the part of this expense above the yearly limit
 * is taxed. In the founding year the limit is 1% of the year's own costs (98⁴.3), known only at
 * year end.
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
            return $o->unresolved($this, "In the founding year the limit is 1% of the year's costs (98⁴.3); assess at year end.");
        }

        $revenue = $o->books->revenue($o->company, $o->year() - 1);

        if ($revenue === null) {
            return $o->unresolved($this, "The limit is 1% of last year's revenue, which the books don't give.");
        }

        $limit = $revenue->scale(0.01);
        $before = $o->ledger->spent($o->company, $o->year(), ExpenseNature::Representation->value);
        $after = $before->plus($o->amount);

        if ($after->atMost($limit)) {
            return $o->notTaxable($this, "Within this year's representation limit of $limit.");
        }

        $excess = Money::min($o->amount, $after->minus($limit));

        return $o->taxed($this, '98.1', $excess, ["Only the $excess above this year's limit of $limit is taxed."]);
    }
}
