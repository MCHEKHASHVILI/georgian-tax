<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax\Rule;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\PropertyTax\Holding;

/** One way property tax is measured. Null when the item isn't this rule's. */
interface Rule
{
    public function assess(Holding $holding): ?Outcome;
}
