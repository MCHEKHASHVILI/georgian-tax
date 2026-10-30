<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Declared;

use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use GeorgianTax\Law\Article;

/**
 * Catch-all: Georgian-source income of an individual that nobody withheld — an entrepreneur's sales,
 * rent from private tenants, a non-resident's income through a Georgian PE (80.2). Declared by
 * 1 April; the figure here is gross, deductions are applied at year end (Chapter XV).
 */
#[Article('80.1', 'Annual taxable income')]
final class GeneralIncome implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        return $a->declared($this, '81.1', Collection::AnnualDeclaration, [
            'Gross amount; deductions under Chapter XV reduce the annual base.',
        ]);
    }
}
