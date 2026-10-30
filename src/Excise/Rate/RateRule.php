<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;

/** One way excise is measured. Null when the goods aren't this rule's. */
interface RateRule
{
    public function levy(Duty $duty): ?Outcome;
}
