<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax\Rule;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;
use GeorgianTax\PropertyTax\Holding;

/**
 * An individual's property (not land) — reached only when the family earned more than 100,000 GEL
 * last year (206.1.ა) — at 0.8% to 1% of its market value at the end of the previous year (202.5),
 * in proportion to the months owned (202.9).
 */
#[Article('202.5', 'Individual property')]
final class IndividualProperty implements Rule
{
    public function assess(Holding $h): ?Outcome
    {
        if (!$h->isIndividual()) {
            return null;
        }

        $value = $h->item()->value;

        if ($value === null) {
            return $h->unresolved($this, "An individual's property is taxed on its market value — give it.");
        }

        return $h->taxed(new Citation('202.5', 'Individual property'), $value, $h->rates->individualPercent);
    }
}
