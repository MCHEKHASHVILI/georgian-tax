<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Withholding;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;

/** A tourist enterprise paying the individual owner of a hotel room. Final tax (133².2). */
#[Article('133².1', 'Tourist enterprise payout to a room owner')]
final class TouristEnterprisePayout implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        return $a->is(IncomeKind::TouristHotelPayout) && $a->payerIs(Status::TouristEnterprise) && $a->taxpayer()->isIndividual()
            ? $a->withheld($this, '133².1')
            : null;
    }
}
