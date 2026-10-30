<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\ImportTax\Clearance;

interface Stage
{
    /** @param Closure(Clearance): Outcome $next */
    public function __invoke(Clearance $clearance, Closure $next): Outcome;
}
