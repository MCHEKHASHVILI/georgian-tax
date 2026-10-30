<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax\Rule;

use GeorgianTax\Domain\Property\PropertyKind as K;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;
use GeorgianTax\PropertyTax\Holding;

/**
 * Non-agricultural land: 0.24 GEL per m² a year times the council's territorial coefficient, at
 * most 1.5 (204.2). Land allotted for natural-resource use: up to 3 GEL per hectare (204.3).
 */
#[Article('204.2–3', 'Non-agricultural and natural-resource land')]
final class OtherLand implements Rule
{
    public function assess(Holding $h): ?Outcome
    {
        $item = $h->item();

        if ($item->kind !== K::NonAgriculturalLand && $item->kind !== K::NaturalResourceLand) {
            return null;
        }

        if ($item->area === null) {
            return $h->unresolved($this, 'Land is taxed by area — give it.');
        }

        if ($item->kind === K::NaturalResourceLand) {
            return $h->levied(new Citation('204.3', 'Natural-resource land'), $item->area, 'ha', $h->rates->naturalResourcePerHa);
        }

        $k = $h->rates->territorialCoefficient($item->municipality);
        $perM2 = $h->land['non_agricultural_per_m2'] * $k;

        return $h->levied(new Citation('204.2', 'Non-agricultural land'), $item->area, 'm²', $perM2, $k === 1.0 ? [] : ["0.24 GEL/m² × coefficient $k."]);
    }
}
