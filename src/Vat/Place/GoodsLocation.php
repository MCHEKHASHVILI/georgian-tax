<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Place;

use GeorgianTax\Vat\Supply;
use Mchekhashvili\TaxLegislation\Law\Article;

/**
 * Goods are supplied where they are at the moment of supply, or where transport to the customer
 * starts. An export is therefore supplied in Georgia — and then exempted with credit (172.4.ე).
 */
#[Article('162.1–2', 'Place of supply of goods')]
final class GoodsLocation implements PlaceRule
{
    public function inGeorgia(Supply $s): ?bool
    {
        return $s->isGoods() ? $s->event->place()->isGeorgia() : null;
    }
}
