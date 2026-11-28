<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\NonResident;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;

/**
 * Rent paid to a non-resident company by someone who is not a tax agent (154.1) is not taxed at
 * source under 134 (97.4 sets 97.11 aside): the company pays profit tax on the year's net income
 * from a Georgian source (97.11), which a `CloseRentalYear` event assesses. Paid by a tax agent, the
 * rent is withheld under 134.1.ე as before.
 */
#[Article('97.11', "Non-resident company's rent from non-agents")]
final class NonResidentCompanyRent implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->taxpayer()->isNonResidentWithoutPE() || $a->taxpayer()->kind !== PartyKind::Company
            || !$a->is(IncomeKind::Rent, IncomeKind::ResidentialRent) || $a->payerCanWithhold()) {
            return null;
        }

        return $a->notTaxable($this, 'Paid by someone who is not a tax agent (154.1), this rent is not taxed at source (97.4); the company pays profit tax on the year (97.11).');
    }
}
