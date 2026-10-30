<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax\Rule;

use GeorgianTax\Domain\Property\PropertyKind as K;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;
use GeorgianTax\PropertyTax\Holding;

/**
 * Agricultural land, per hectare, by municipality (204.1): arable and homestead land from 56 to
 * 100 GEL; hayfields 16–20; pastures 5–16. The council may raise the base by up to 50% (204.1.დ).
 * Forest land used in agriculture is taxed as the category it is used as (204.1.გ).
 */
#[Article('204.1', 'Agricultural land')]
final class AgriculturalLand implements Rule
{
    public function assess(Holding $h): ?Outcome
    {
        $item = $h->item();

        [$table, $ref] = match ($item->kind) {
            K::Arable, K::Homestead => ['arable', '204.1.ა'],
            K::Hayfield => ['hayfield', '204.1.ბ'],
            K::Pasture => ['pasture', '204.1.ბ'],
            default => [null, null],
        };

        if ($table === null) {
            return null;
        }

        $base = $h->land[$table][strtolower((string) $item->municipality)] ?? null;

        if ($base === null || $item->area === null) {
            return $h->unresolved(new Citation($ref), "No 204.1 rate for '{$item->municipality}' — give the land's area and municipality.");
        }

        $multiplier = $h->rates->landMultiplier($item->municipality);
        $notes = $multiplier === 1.0 ? [] : ["Base $base GEL/ha × council multiplier $multiplier."];

        return $h->levied(new Citation($ref, $item->kind->name), $item->area, 'ha', $base * $multiplier, $notes);
    }
}
