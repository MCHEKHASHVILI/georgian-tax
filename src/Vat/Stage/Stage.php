<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Vat\Supply;

/** One step of the VAT pipeline. Answer, or pass to $next. */
interface Stage
{
    /** @param Closure(Supply): Outcome $next */
    public function handle(Supply $supply, Closure $next): Outcome;
}
