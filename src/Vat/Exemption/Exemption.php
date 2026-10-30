<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Exemption;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Vat\Supply;

/** One VAT exemption. Returns Exempt or ExemptWithCredit when it applies, null otherwise. */
interface Exemption
{
    public function exempts(Supply $supply): ?Outcome;
}
