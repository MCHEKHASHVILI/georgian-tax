<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\PropertyTax\Holding;

interface Stage
{
    /** @param Closure(Holding): Outcome $next */
    public function handle(Holding $holding, Closure $next): Outcome;
}
