<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Stage;

use Closure;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;

/** One step of the income tax pipeline. Answer, or pass to $next. */
interface Stage
{
    /** @param Closure(Assessment): Outcome $next */
    public function handle(Assessment $assessment, Closure $next): Outcome;
}
