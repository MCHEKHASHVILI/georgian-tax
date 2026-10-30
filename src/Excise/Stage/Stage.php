<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;

interface Stage
{
    /** @param Closure(Duty): Outcome $next */
    public function __invoke(Duty $duty, Closure $next): Outcome;
}
