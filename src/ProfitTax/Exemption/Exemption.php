<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Exemption;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\ProfitTax\Outflow;

/** Releases an outflow from profit tax: Exempt or NotTaxable when it applies, null otherwise. */
interface Exemption
{
    public function exempts(Outflow $outflow): ?Outcome;
}
