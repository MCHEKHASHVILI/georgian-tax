<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Withholding;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use GeorgianTax\Law\Article;

/**
 * Paying an individual who isn't a registered entrepreneur for a service — rent included (Art. 14.1.ზ).
 * Not withheld for VAT payers, notaries and private bailiffs (or micro/small/fixed-tax payers,
 * whom ApplySpecialRegime already took); they declare instead.
 * Residential rent registered without deductions is taxed at 5% (81.2).
 */
#[Article('154.1.დ', 'Service from an individual')]
final class ServiceFromIndividual implements Rule
{
    private const NOT_WITHHELD = [Status::VatPayer, Status::Notary, Status::PrivateBailiff];

    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->kind()->isService() || $a->taxpayer()->kind !== PartyKind::Individual) {
            return null;
        }

        $rate = $a->is(IncomeKind::ResidentialRent) && $a->payment()?->noDeductionsOnResidentialRent ? '81.2' : '81.1';

        foreach (self::NOT_WITHHELD as $status) {
            if ($a->taxpayer()->is($status)) {
                return $a->declared($this, $rate, Collection::AnnualDeclaration, ["Not withheld for a {$status->name} (154.1.დ)."]);
            }
        }

        return $a->withheld($this, $rate);
    }
}
