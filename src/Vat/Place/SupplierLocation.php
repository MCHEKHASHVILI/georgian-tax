<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Place;

use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\Vat\Supply;

/** General rule for consumers: where the supplier is established, or its fixed establishment that supplies. */
#[Article('162¹.3.ბ', 'Services to a consumer')]
final class SupplierLocation implements PlaceRule
{
    public function inGeorgia(Supply $s): ?bool
    {
        $supplier = $s->supplier();

        return $supplier->country === 'GE' || $supplier->hasGeorgianPE;
    }
}
