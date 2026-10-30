<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Rule;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\ImportTax\Clearance;

/** One way import tax is measured. Null when the goods aren't this rule's. */
interface Rule
{
    public function assess(Clearance $clearance): ?Outcome;
}
