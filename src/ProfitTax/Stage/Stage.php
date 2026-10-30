<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\ProfitTax\Outflow;

interface Stage
{
    /** @param Closure(Outflow): Outcome $next */
    public function __invoke(Outflow $outflow, Closure $next): Outcome;
}
