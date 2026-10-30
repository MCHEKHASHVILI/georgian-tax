<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Source;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\Law\Article;

#[Article('104.1.ა', 'Salary for work in Georgia')]
final class WorkInGeorgia implements SourceRule
{
    public function decide(Assessment $a): ?bool
    {
        return $a->is(IncomeKind::Salary) ? $a->event->place()->isGeorgia() : null;
    }
}
