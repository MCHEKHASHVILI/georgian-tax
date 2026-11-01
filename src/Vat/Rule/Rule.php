<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Rule;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\Vat\Supply;

/** Decides who owes VAT on a taxable, non-exempt supply in Georgia, and when. Null when it doesn't apply. */
interface Rule
{
    public function assess(Supply $supply): ?Outcome;
}
