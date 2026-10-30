<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Source;

use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\Law\Article;

/**
 * Provider and recipient are in different countries and the recipient is a Georgian resident:
 * the service counts as performed in Georgia even when done abroad — unless the provider proves
 * it was performed through its permanent establishment in another country.
 */
#[Article('104.1.გ.ზ', 'Cross-border service to a Georgian resident')]
final class CrossBorderServiceToResident implements SourceRule
{
    public function decide(Assessment $a): ?bool
    {
        $payer = $a->payer();

        if (!$a->kind()->isService() || $payer === null || !$payer->isResident()) {
            return null;
        }

        if ($payer->country === $a->taxpayer()->country) {
            return null;
        }

        return !$a->event->place()->providerProvedForeignPE;
    }
}
