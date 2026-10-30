<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Exemption;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Money;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\Law\Citation;

/**
 *   82.1.ღ   a lottery win of 1,000 GEL or less;
 *   82.1.ძ¹  foreign citizens' winnings and withdrawals from online gambling;
 *   82.1.ბ⁴.ა private pension contributions by or for a participant, up to 6,000 GEL a year.
 */
final class WinningsAndPensions implements Exemption
{
    public function exempts(Assessment $a): ?Exempt
    {
        if ($a->is(IncomeKind::LotteryWin) && $a->income()->atMost(Money::gel(1000))) {
            return $a->exempt(new Citation('82.1.ღ'), 'A lottery win of 1,000 GEL or less is exempt.');
        }

        if ($a->is(IncomeKind::GamingAccountWithdrawal) && $a->taxpayer()->citizenship !== 'GE') {
            return $a->exempt(new Citation('82.1.ძ¹'), "Foreign citizens' online gambling winnings are exempt.");
        }

        if ($a->is(IncomeKind::PrivatePensionContribution)
            && $a->ledger->received($a->taxpayer(), $a->year(), IncomeKind::PrivatePensionContribution)
                ->plus($a->income())->atMost(Money::gel(6000))) {
            return $a->exempt(new Citation('82.1.ბ⁴.ა'), 'Private pension contributions up to 6,000 GEL a year are exempt.');
        }

        return null;
    }
}
