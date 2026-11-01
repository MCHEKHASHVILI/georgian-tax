<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Exemption;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\Vat\Supply;

/**
 * Goods supplied in Georgia and transported abroad. Counts toward the registration threshold
 * (165.7.ბ). Passenger luggage exports need a non-resident, 90 days and a price over 600 GEL —
 * set Place::shippedTo only when those are met.
 */
#[Article('172.4.ე', 'Export of goods')]
final class Export implements Exemption
{
    public function exempts(Supply $s): ?Outcome
    {
        return $s->isGoods() && !$s->isImport() && $s->event->place()->isExport()
            ? $s->exemptWithCredit($this, 'Exported goods — no VAT, input credit kept.', countsTowardThreshold: true)
            : null;
    }
}
