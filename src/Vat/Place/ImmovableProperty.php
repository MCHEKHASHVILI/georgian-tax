<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Place;

use GeorgianTax\Domain\Income\SupplyCategory;
use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\Vat\Supply;

/** Services linked to immovable property — including estate agents, experts and site supervision — follow the property. */
#[Article('162¹.4', 'Services linked to immovable property')]
final class ImmovableProperty implements PlaceRule
{
    public function inGeorgia(Supply $s): ?bool
    {
        $place = $s->event->place();

        return match (true) {
            $place->tiedToPropertyAbroad => false,
            $place->tiedToPropertyInGeorgia, $s->is(SupplyCategory::ImmovablePropertyService) => true,
            default => null,
        };
    }
}
