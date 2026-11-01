<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Source;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\IncomeTax\Assessment;
use Mchekhashvili\TaxLegislation\Law\Article;

/**
 * Dividends from a resident company (104.1.ვ), interest and royalty paid by a resident or charged to a
 * Georgian PE (ზ, ი), pensions and scholarships from a resident (თ), management and insurance fees
 * from a resident or a Georgian PE (ო).
 */
#[Article('104.1.ვ–ო', 'Passive income paid by a resident')]
final class PaidByResident implements SourceRule
{
    private const KINDS = [
        IncomeKind::Dividend, IncomeKind::Interest, IncomeKind::Royalty, IncomeKind::Insurance,
        IncomeKind::Pension, IncomeKind::Scholarship, IncomeKind::PrivatePensionPayout,
        IncomeKind::PrivatePensionEarlyWithdrawal, IncomeKind::AccumulativePensionPayout,
        IncomeKind::AccumulativePensionRefund, IncomeKind::PrivatePensionContribution,
    ];

    public function decide(Assessment $a): ?bool
    {
        if (!$a->is(...self::KINDS)) {
            return null;
        }

        $payer = $a->payer();

        return $payer !== null && ($payer->isResident() || $payer->hasGeorgianPE);
    }
}
