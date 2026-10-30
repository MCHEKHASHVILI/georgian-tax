<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Withholding;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use GeorgianTax\Law\Article;

/** The buyer withholds 3% when buying goods on the Minister of Finance's list from an individual without a waybill. Final tax (133³.3). */
#[Article('133³.1', 'Listed goods bought from an individual')]
final class ListedGoodsWithoutWaybill implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        $payment = $a->payment();

        return $a->is(IncomeKind::GoodsSupply) && $a->taxpayer()->kind === PartyKind::Individual
            && $payment?->listedGoods && !$payment->waybillIssued
            ? $a->withheld($this, '133³.1')
            : null;
    }
}
