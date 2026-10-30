<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\NonResident;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use GeorgianTax\Law\Article;

/**
 * A non-resident's salary for work in Georgia, at the Art. 81 rate. A foreign employer without a
 * Georgian PE doesn't withhold (154.1.ა.ბ); the employee may pay it monthly instead (154.5).
 */
#[Article('134.1.ვ', "Non-resident's salary")]
final class NonResidentSalary implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->taxpayer()->isNonResidentWithoutPE() || !$a->is(IncomeKind::Salary)) {
            return null;
        }

        $employer = $a->payer();

        if ($employer !== null && !$employer->isResident() && !$employer->hasGeorgianPE && !$a->payment()?->chargedToGeorgianPE) {
            return $a->declared($this, '81.1', Collection::MonthlyDeclaration, [
                'A foreign employer without a Georgian PE does not withhold (154.1.ა.ბ); the employee pays (154.5).',
            ]);
        }

        return $a->withheld($this, '81.1');
    }
}
