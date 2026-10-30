<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Rule;

use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Article;
use GeorgianTax\Vat\Supply;

/**
 * Anyone importing pays, registered or not (165².2.ა), with the import duties (168.2) — or within
 * 45 days for Government-listed HS 8401–9033 goods (168.3). A registered importer credits it (175.1.გ).
 */
#[Article('165².2.ა', 'VAT on import')]
final class ImportVat implements Rule
{
    public function assess(Supply $s): ?Outcome
    {
        if (!$s->event instanceof Import) {
            return null;
        }

        return $s->charged($this, $s->event->onDeferredPaymentList ? Collection::AtCustomsDeferred : Collection::AtCustoms);
    }
}
