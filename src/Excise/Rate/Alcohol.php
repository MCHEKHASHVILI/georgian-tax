<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Excise\ExciseRules;
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
 * no excise; neither does anything at 1.2% or less (beer: 0.5%). An "other fermented drink" is
 * over 1.2% and up to 10%, or over 10% and up to 15% when fully fermented (note ბ); the rest of
 * 2205 and 2206 up to 22% is intermediate (note გ). The rates are rows of ExciseRules::ALCOHOL by
 * row number; while rows 2 and 4 agree, whether a 10–15% drink is fully fermented changes nothing.
 */
#[Article('188¹', 'Alcoholic drinks')]
final class Alcohol implements RateRule
{
    private const BEER_MIXES = ['22060059001', '22060089001'];
    private const LOWER_SPIRITS = ['220820', '220860', '220890330', '220890380', '220890480', '220890710'];

    private const LABELS = [1 => 'beer', 2 => 'other fermented drink over 5%', 3 => 'other fermented drink up to 5%',
        4 => 'intermediate drink', 5 => 'high-strength drink', 6 => 'ethyl alcohol', 7 => 'brandy, rum, liqueur and the like'];

    public function levy(Duty $d): ?Outcome
    {
        $c = $d->commodity;
        $abv = $c->alcoholPercent ?? 0.0;
        $any = static fn (array $codes): bool => array_any($codes, $c->startsWith(...));

        $isBeer = $c->startsWith('2203') || $any(self::BEER_MIXES);
        $isFermented = $c->startsWith('2205') || $c->startsWith('2206');

        $row = match (true) {
            $isBeer && $abv <= 0.5 => $d->notTaxable(new Citation('188¹.1', 'note ა'), 'Beer of 0.5% or less is not excise goods.'),
            $isBeer => 1,
            $c->startsWith('2204') => $d->notTaxable(new Citation('188¹.2'), 'Wine is not excise goods.'),
            $c->startsWith('2207') => 6,
            ($isFermented || $c->startsWith('2208')) && $abv <= 1.2
                => $d->notTaxable(new Citation('188¹.1', 'notes ბ–დ'), 'Drinks of 1.2% or less are not excise goods.'),
            $isFermented && $abv <= 5 => 3,
            $isFermented && $abv <= 10 => 2,
            $isFermented && $abv <= 15 => $this->tenToFifteen($d),
            $isFermented && $abv <= 22 => 4,
            $c->startsWith('2208') && $any(self::LOWER_SPIRITS) => 7,
            $isFermented || $c->startsWith('2208') => 5,
            default => null,
        };

        if (!is_int($row)) {
            return $row;
        }

        $rate = $d->rules->rate(ExciseRules::ALCOHOL, (string) $row);
        $basis = new Citation('188¹.1', "Row $row — " . self::LABELS[$row]);

        return match (true) {
            $rate === null => $d->noRate($basis, self::LABELS[$row]),
            $row === 1 => $d->levied($basis, $c->quantity * $abv, 'litre × % vol', $rate),
            $row <= 4 => $d->levied($basis, $c->quantity, 'litre', $rate),
            default => $d->levied($basis, $c->pureAlcoholLitres(), 'litre pure alcohol', $rate),
        };
    }

    /** Over 10% and up to 15%: row 2 if fully fermented (note ბ.ბ), otherwise row 4 (note გ). */
    private function tenToFifteen(Duty $d): int|Outcome
    {
        if ($d->commodity->fullyFermented !== null) {
            return $d->commodity->fullyFermented ? 2 : 4;
        }

        $fermented = $d->rules->rate(ExciseRules::ALCOHOL, '2');

        return $fermented !== null && $fermented === $d->rules->rate(ExciseRules::ALCOHOL, '4')
            ? 2
            : $d->notTaxable(new Citation('188¹.1', 'note ბ.ბ'), 'A drink over 10% and up to 15% needs to say whether its alcohol is fully fermented: rows 2 and 4 differ.');
    }
}
