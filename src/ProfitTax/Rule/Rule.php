<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Rule;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\ProfitTax\Outflow;

interface Rule
{
    public function assess(Outflow $outflow): ?Outcome;
}
