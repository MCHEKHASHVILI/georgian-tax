<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax\Rule;

use GeorgianTax\Domain\Property\PropertyKind as K;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;
use GeorgianTax\PropertyTax\Holding;

/**
 * Non-agricultural land: 0.24 GEL per m² a year times the council's territorial coefficient, at
 * most 1.5 (204.2). Land allotted for natural-resource use: up to 3 GEL per hectare (204.3).
 * Rates and caps as in force on 1 April of the tax year (204.4).
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
            $perHa = $h->councilRate('204.3', $h->rates->naturalResourcePerHa);

            return $perHa instanceof Unresolved ? $perHa : $h->levied(new Citation('204.3', 'Natural-resource land'), $item->area, 'ha', $perHa);
        }

        $k = $h->councilRate('204.2.ბ', $h->rates->territorialCoefficient($item->municipality), default: 1.0);

        if ($k instanceof Unresolved) {
            return $k;
        }

        $base = $h->land['non_agricultural_per_m2'];

        return $h->levied(new Citation('204.2', 'Non-agricultural land'), $item->area, 'm²', $base * $k, $k === 1.0 ? [] : ["$base GEL/m² × coefficient $k."]);
    }
}
