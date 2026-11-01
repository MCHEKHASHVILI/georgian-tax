<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Withholding;

use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 *   154.1.ე   promo draws, lotto, bingo and lotteries withhold 20% of the win;
 *   154.1.ე¹  gaming halls and online gambling withhold 5% of each withdrawal (80.8, 81.3²).
 */
#[Article('154.1.ე, ე¹', 'Winnings')]
final class Winnings implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        return match (true) {
            $a->is(IncomeKind::LotteryWin, IncomeKind::PromoDrawWin) => $a->withheld(new Citation('154.1.ე'), '81.1'),
            $a->is(IncomeKind::GamingAccountWithdrawal) => $a->withheld(new Citation('154.1.ე¹'), '81.3²'),
            default => null,
        };
    }
}
