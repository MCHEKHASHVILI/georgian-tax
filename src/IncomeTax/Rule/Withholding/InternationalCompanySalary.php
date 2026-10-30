<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Withholding;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use GeorgianTax\Law\Article;

#[Article('23.7', 'Salary from an international company')]
final class InternationalCompanySalary implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        return $a->is(IncomeKind::Salary) && $a->payerIs(Status::InternationalCompany)
            ? $a->withheld($this, '23.7')
            : null;
    }
}
