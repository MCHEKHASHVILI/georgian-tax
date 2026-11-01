<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Exemption;

use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Regime;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\Law\Citation;

/**
 *   82.1.ა  a non-resident's salary at a foreign diplomatic mission in Georgia;
 *   82.1.შ  a non-resident working in Georgia 30 days or less a year for a non-resident employer,
 *           unless the cost belongs to the employer's Georgian PE;
 *   82.1.ც  salary in agricultural production, employer's agricultural revenue ≤ 200,000 GEL, until 2028;
 *   82.1.ჰ¹ salary from an employer in bankruptcy;
 *   94.4    the first 6,000 GEL a year a small business pays in salaries, if it registered this year
 *           or its prior-year revenue was ≤ 50,000 GEL.
 */
final class SalaryExemptions implements Exemption
{
    public function exempts(Assessment $a): ?Exempt
    {
        if (!$a->is(IncomeKind::Salary) || ($payer = $a->payer()) === null) {
            return null;
        }

        $employee = $a->taxpayer();
        $payment = $a->payment();

        if (!$employee->isResident() && $payer->is(Status::DiplomaticMission)) {
            return $a->exempt(new Citation('82.1.ა'), "A non-resident's salary at a foreign diplomatic mission is exempt.");
        }

        if (!$employee->isResident() && !$payer->isResident() && !$payer->hasGeorgianPE
            && !($payment?->chargedToGeorgianPE) && ($payment?->daysWorkedInGeorgia ?? PHP_INT_MAX) <= 30) {
            return $a->exempt(new Citation('82.1.შ'), 'Up to 30 days of work in Georgia for a foreign employer is exempt.');
        }

        if ($payer->is(Status::AgriculturalProducer) && $a->before('2028-01-01')
            && $a->ledger->received($payer, $a->year(), IncomeKind::GoodsSupply)->lessThanOrEqual(Money::GEL(200_000))) {
            return $a->exempt(new Citation('82.1.ც'), 'Salary in small-scale agricultural production is exempt until 2028.');
        }

        if ($payer->is(Status::Bankrupt)) {
            return $a->exempt(new Citation('82.1.ჰ¹'), 'Salary from an employer in bankruptcy is exempt.');
        }

        if ($payer->regime === Regime::SmallBusiness && $this->smallEmployerQualifies($a)
            && $a->ledger->paid($payer, $a->year(), IncomeKind::Salary)->plus($a->income())->lessThanOrEqual(Money::GEL(6000))) {
            return $a->exempt(new Citation('94.4'), "The first 6,000 GEL a year of a small business's salaries is not taxed at source.");
        }

        return null;
    }

    private function smallEmployerQualifies(Assessment $a): bool
    {
        $payer = $a->payer();
        $registered = $payer->since(Status::RegisteredTaxpayer);

        return ($registered !== null && (int) $registered->format('Y') === $a->year())
            || $a->ledger->received($payer, $a->year() - 1)->lessThanOrEqual(Money::GEL(50_000));
    }
}
