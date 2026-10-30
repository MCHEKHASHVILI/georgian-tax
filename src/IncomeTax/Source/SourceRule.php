<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Source;

use GeorgianTax\IncomeTax\Assessment;

/** One paragraph of Art. 104. Returns true/false when it decides, null when it doesn't apply. */
interface SourceRule
{
    public function decide(Assessment $assessment): ?bool;
}
