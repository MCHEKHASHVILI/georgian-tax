<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;

/**
 * One taxing rule of the Code, tagged with #[Article]. Returns null when it doesn't apply,
 * otherwise finishes the assessment with $a->withheld(...), $a->declared(...) or $a->unresolved(...).
 */
interface Rule
{
    public function assess(Assessment $assessment): ?Outcome;
}
