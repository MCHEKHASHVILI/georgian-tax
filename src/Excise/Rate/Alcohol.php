<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Alcoholic drinks (188¹). Quantity in litres; strength in % vol.
 *
 *   1 beer — 2203 and beer mixes 2206 00 590 01 / 890 01, over 0.5%   0.12 GEL per litre per % vol (188¹.3)
 *   2 other fermented drinks (2205, 2206) over 5%                       5.00 GEL per litre
 *   3 other fermented drinks 5% or less                                 0.60 GEL per litre
 *   4 intermediate drinks (2205, 2206) up to 22%                        5.00 GEL per litre
 *   5 high-strength drinks: 2208 over 1.2%, 2205/2206 over 22%          22.50 GEL per litre of pure alcohol
 *   6 ethyl alcohol (2207)                                              7.50 GEL per litre of pure alcohol
 *   7 2208 20, 2208 60, 2208 90 330/380/480/710                          15.00 GEL per litre of pure alcohol
 *
 * Wine (2204, and grape 2205 up to 18% fully fermented — 188¹.2) is not in the table and carries
 * no excise; neither does anything at 1.2% or less (beer: 0.5%). Rows 2 and 4 share a rate, so the
 * "fully fermented" test that separates them does not change the amount.
 */
#[Article('188¹', 'Alcoholic drinks')]
final class Alcohol implements RateRule
{
    private const BEER_MIXES = ['22060059001', '22060089001'];
    private const LOWER_SPIRITS = ['220820', '220860', '220890330', '220890380', '220890480', '220890710'];

    public function levy(Duty $d): ?Outcome
    {
        $c = $d->commodity;
        $abv = $c->alcoholPercent ?? 0.0;
        $any = static fn (array $codes): bool => array_any($codes, $c->startsWith(...));
        $row = static fn (int $n, string $label): Citation => new Citation('188¹.1', "Row $n — $label");

        $isBeer = $c->startsWith('2203') || $any(self::BEER_MIXES);
        $isFermented = $c->startsWith('2205') || $c->startsWith('2206');

        return match (true) {
            $isBeer && $abv <= 0.5 => $d->notTaxable(new Citation('188¹.1', 'note ა'), 'Beer of 0.5% or less is not excise goods.'),
            $isBeer => $d->levied($row(1, 'beer'), $c->quantity * $abv, 'litre × % vol', 0.12),
            $c->startsWith('2204') => $d->notTaxable(new Citation('188¹.2'), 'Wine is not excise goods.'),
            $c->startsWith('2207') => $d->levied($row(6, 'ethyl alcohol'), $c->pureAlcoholLitres(), 'litre pure alcohol', 7.5),
            ($isFermented || $c->startsWith('2208')) && $abv <= 1.2
                => $d->notTaxable(new Citation('188¹.1', 'notes ბ–დ'), 'Drinks of 1.2% or less are not excise goods.'),
            $isFermented && $abv <= 5 => $d->levied($row(3, 'fermented drink up to 5%'), $c->quantity, 'litre', 0.6),
            $isFermented && $abv <= 22 => $d->levied($row(2, 'fermented or intermediate drink'), $c->quantity, 'litre', 5.0),
            $c->startsWith('2208') && $any(self::LOWER_SPIRITS)
                => $d->levied($row(7, 'brandy, rum, liqueur and the like'), $c->pureAlcoholLitres(), 'litre pure alcohol', 15.0),
            $isFermented || $c->startsWith('2208')
                => $d->levied($row(5, 'high-strength drink'), $c->pureAlcoholLitres(), 'litre pure alcohol', 22.5),
            default => null,
        };
    }
}
