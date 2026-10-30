<?php

declare(strict_types=1);

namespace GeorgianTax\Engine\Outcome;

use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;

/**
 * An outcome that adds to its taxpayer's turnover for a threshold — e.g. the 100,000 GEL VAT
 * registration line counts taxable supplies even before the supplier is registered (165.1, 165.7).
 */
interface CountsAsTurnover
{
    public function turnoverOf(): ?Party;

    public function turnover(): Money;
}
