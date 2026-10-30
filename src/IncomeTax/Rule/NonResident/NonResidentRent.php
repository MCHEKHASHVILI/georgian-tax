<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\NonResident;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use GeorgianTax\Law\Article;

/**
 * Rent paid to a non-resident individual is taxed at the Art. 81 rate. When the payer isn't a tax
 * agent, the landlord declares net income instead (80.6) — withheld() handles that switch.
 */
#[Article('134.1.დ¹', 'Rent paid to a non-resident individual')]
final class NonResidentRent implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->taxpayer()->isNonResidentWithoutPE() || !$a->taxpayer()->isIndividual()
            || !$a->is(IncomeKind::Rent, IncomeKind::ResidentialRent)) {
            return null;
        }

        $residentialFivePercent = $a->is(IncomeKind::ResidentialRent) && $a->payment()?->noDeductionsOnResidentialRent;

        return $a->withheld($this, $residentialFivePercent ? '81.2' : '81.1');
    }
}
