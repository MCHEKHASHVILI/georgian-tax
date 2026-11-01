<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Exemption;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use GeorgianTax\IncomeTax\Assessment;

/** One exemption of the Code. Returns the Exempt outcome when it applies, null otherwise. */
interface Exemption
{
    public function exempts(Assessment $assessment): ?Exempt;
}
