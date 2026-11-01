<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Withholding;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;

/** Not withheld for a VAT-registered individual, who declares it instead. An entrepreneur may credit the tax (132.3). */
#[Article('132.1', 'Royalty to a resident individual')]
final class RoyaltyToResident implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->is(IncomeKind::Royalty) || !$a->taxpayer()->isResident()) {
            return null;
        }

        return $a->taxpayer()->is(Status::VatPayer)
            ? $a->declared($this, '81.1', Collection::AnnualDeclaration, ['VAT-registered individuals are not withheld on royalties.'])
            : $a->withheld($this, '132.1');
    }
}
