<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Stage;

use Closure;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\ImportTax\Clearance;

interface Stage
{
    /** @param Closure(Clearance): Outcome $next */
    public function handle(Clearance $clearance, Closure $next): Outcome;
}
