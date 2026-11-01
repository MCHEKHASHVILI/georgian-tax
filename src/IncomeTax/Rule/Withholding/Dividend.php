<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Withholding;

use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;

/** To individuals, non-profits and non-resident companies alike (134.1.ა points here). Final tax (130.3). */
#[Article('130.1', 'Dividend')]
final class Dividend implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        return $a->is(IncomeKind::Dividend) ? $a->withheld($this, '130.1') : null;
    }
}
