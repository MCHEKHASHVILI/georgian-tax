<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\NonResident;

use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;

#[Article('134.1.ბ¹', 'Royalty to a non-resident')]
final class NonResidentRoyalty implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->taxpayer()->isNonResidentWithoutPE() || !$a->is(IncomeKind::Royalty)) {
            return null;
        }

        return $a->withheld($this, $a->nonResidentRate('134.1.ბ¹'));
    }
}
