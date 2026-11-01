<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Withholding;

use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;

/**
 * Interest paid by a resident or a Georgian PE to an individual or a non-resident without a PE
 * (134.1.ბ points here). Final tax for individuals (131.3). 15% to preferential-tax countries (134.1¹).
 */
#[Article('131.1', 'Interest')]
final class Interest implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->is(IncomeKind::Interest)) {
            return null;
        }

        $rate = $a->taxpayer()->isResident() ? '131.1' : $a->nonResidentRate('131.1');

        return $a->withheld($this, $rate);
    }
}
