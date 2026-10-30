<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use GeorgianTax\Domain\Money;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;

/**
 * Cigarettes, cigarillos and heated tobacco: a specific rate per 20 plus a share of the retail
 * price set each December for the next year (188.1¹):
 *   cigarettes (2402 20)                2.75 per 20 + 20%  — local quota cigarettes 1.30 + 15%
 *   cigarillos (2402 10 000 02)         2.40 per 20 + 30%
 *   heated tobacco, capsules (2404 11)  2.70 per 20 + 20%
 * Quantity is counted in sticks; the rate applies per 20.
 */
#[Article('188.1¹', 'Cigarettes, cigarillos and heated tobacco')]
final class Tobacco implements RateRule
{
    public function levy(Duty $d): ?Outcome
    {
        $c = $d->commodity;

        [$article, $perTwenty, $share, $label] = match (true) {
            $c->startsWith('240220') && $c->localQuotaCigarettes => ['188.1¹.ა¹', 1.30, 0.15, 'Local quota cigarettes'],
            $c->startsWith('240220') => ['188.1¹.ა', 2.75, 0.20, 'Cigarettes'],
            $c->startsWith('24021000002') => ['188.1¹.გ', 2.40, 0.30, 'Cigarillos'],
            $c->startsWith('240411') => ['188.1¹.ბ', 2.70, 0.20, 'Heated tobacco'],
            default => [null, 0, 0, ''],
        };

        if ($article === null) {
            return null;
        }

        if ($c->retailPricePer20 === null) {
            return $d->notTaxable(new Citation($article), "$label need a retail price per 20 to compute the ad-valorem part.");
        }

        $packs = $c->quantity / 20;
        $adValorem = Money::gel($packs * $c->retailPricePer20 * $share);

        return $d->levied(new Citation($article, $label), $packs, 'packs of 20', $perTwenty, $adValorem, [
            "Plus {$share}× the retail price of {$c->retailPricePer20} GEL per 20: $adValorem.",
        ]);
    }
}
