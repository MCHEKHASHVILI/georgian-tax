<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\NonResident;

use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;

#[Article('134.1.დ', 'Non-resident oil and gas subcontractor')]
final class NonResidentOilGas implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->taxpayer()->isNonResidentWithoutPE() || !$a->is(IncomeKind::OilGasSubcontract)) {
            return null;
        }

        return $a->withheld($this, '134.1.დ');
    }
}
