<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\NonResident;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;

/**
 * Catch-all for non-residents without a Georgian PE — including services sourced in Georgia by
 * 104.1.გ.ზ. The recipient may file by 1 April for a recalculation on net income (134.3).
 */
#[Article('134.1.ე', 'Other Georgian-source income of a non-resident')]
final class NonResidentOtherIncome implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->taxpayer()->isNonResidentWithoutPE()) {
            return null;
        }

        return $a->withheld($this, $a->nonResidentRate('134.1.ე'), [
            'The recipient may ask for a recalculation on net income by 1 April next year (134.3).',
        ]);
    }
}
