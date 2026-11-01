<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Exemption;

use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use GeorgianTax\IncomeTax\Assessment;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Income released by the recipient's status:
 *   82.1.ჰ   a person in bankruptcy, after the bankruptcy regime opened;
 *   82.1.ჰ³  a high-mountain enterprise, for 10 calendar years from the status (inclusive);
 *   82.1.ჰ⁵  a special enterprise trading with the occupied territories;
 *   82.1.ლ   a farmer's primary supply of own produce, up to 200,000 GEL a year, until 2028;
 *   82.1.რ   a non-resident's insurance and reinsurance income;
 *   82.1.ს   a non-resident's leasing income not tied to a Georgian PE.
 */
final class StatusExemptions implements Exemption
{
    public function exempts(Assessment $a): ?Exempt
    {
        $recipient = $a->taxpayer();

        if ($recipient->is(Status::Bankrupt)) {
            return $a->exempt(new Citation('82.1.ჰ'), 'Income received in bankruptcy is exempt.');
        }

        $mountainSince = $recipient->since(Status::HighMountainEnterprise);

        if ($mountainSince !== null && $a->kind()->isBusinessIncome() && $a->year() <= (int) $mountainSince->format('Y') + 9) {
            return $a->exempt(new Citation('82.1.ჰ³'), 'A high-mountain enterprise is exempt for 10 years.');
        }

        if ($recipient->is(Status::SpecialEnterprise) && $a->kind()->isBusinessIncome()) {
            return $a->exempt(new Citation('82.1.ჰ⁵'), "A special enterprise's trade with the occupied territories is exempt.");
        }

        if ($recipient->is(Status::AgriculturalProducer) && $a->is(IncomeKind::GoodsSupply) && $a->before('2028-01-01')
            && $a->ledger->received($recipient, $a->year(), IncomeKind::GoodsSupply)->plus($a->income())->lessThanOrEqual(Money::GEL(200_000))) {
            return $a->exempt(new Citation('82.1.ლ'), 'Primary supply of own agricultural produce is exempt up to 200,000 GEL a year until 2028.');
        }

        if ($recipient->isNonResidentWithoutPE() && $a->is(IncomeKind::Insurance)) {
            return $a->exempt(new Citation('82.1.რ'), "A non-resident's insurance and reinsurance income is exempt.");
        }

        if ($recipient->isNonResidentWithoutPE() && $a->is(IncomeKind::Leasing)) {
            return $a->exempt(new Citation('82.1.ს'), "A non-resident's leasing income is exempt.");
        }

        return null;
    }
}
