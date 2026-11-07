<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Excise\ExciseRules;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Cigarettes, cigarillos and heated tobacco: a specific rate per 20 plus a share of the retail
 * price set each December for the next year (188.1¹):
 *   cigarettes (2402 20)                2.75 per 20 + 20%  — local quota cigarettes 1.30 + 15%
 *   cigarillos (2402 10 000 02)         2.40 per 20 + 30%
 *   heated tobacco, capsules (2404 11)  2.70 per 20 + 20%
 * Quantity is counted in sticks; the rate applies per 20. The specific rate (from the 188.1 table)
 * and the share are one row each of ExciseRules::TOBACCO.
 */
#[Article('188.1¹', 'Cigarettes, cigarillos and heated tobacco')]
final class Tobacco implements RateRule
{
    public function levy(Duty $d): ?Outcome
    {
        $c = $d->commodity;

        [$article, $key, $label] = match (true) {
            $c->startsWith('240220') && $c->localQuotaCigarettes => ['188.1¹.ა¹', 'local_quota', 'Local quota cigarettes'],
            $c->startsWith('240220') => ['188.1¹.ა', 'cigarettes', 'Cigarettes'],
            $c->startsWith('24021000002') => ['188.1¹.გ', 'cigarillos', 'Cigarillos'],
            $c->startsWith('240411') => ['188.1¹.ბ', 'heated', 'Heated tobacco'],
            default => [null, null, ''],
        };

        if ($article === null) {
            return null;
        }

        $perTwenty = $d->rules->rate(ExciseRules::TOBACCO, $key);
        $percent = $d->rules->percent(ExciseRules::TOBACCO, $key);

        if ($perTwenty === null || $percent === null) {
            return $d->noRate(new Citation($article), strtolower($label));
        }

        if ($c->retailPricePer20 === null) {
            return $d->notTaxable(new Citation($article), "$label need a retail price per 20 to compute the ad-valorem part.");
        }

        $share = $percent / 100;
        $packs = $c->quantity / 20;
        $adValorem = Money::GEL($packs * $c->retailPricePer20 * $share);

        return $d->levied(new Citation($article, $label), $packs, 'packs of 20', $perTwenty, $adValorem, [
            "Plus {$share}× the retail price of {$c->retailPricePer20} GEL per 20: $adValorem.",
        ]);
    }
}
