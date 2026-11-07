<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Excise\ExciseRules;
use Mchekhashvili\TaxLegislation\Law\Citation;
use Mchekhashvili\TaxLegislation\Law\Article;

/**
 * Terminating an international call on a Georgian network, per minute (188.3): 15 tetri on
 * mobile, 8 on fixed. Describe it as a Commodity with Calls::MOBILE or Calls::FIXED as the
 * code and minutes as the quantity. The rates are rows of ExciseRules::CALLS.
 */
#[Article('188.3', 'International call termination')]
final class Calls implements RateRule
{
    public const MOBILE = 'CALL-MOBILE';
    public const FIXED = 'CALL-FIXED';

    public function levy(Duty $d): ?Outcome
    {
        [$key, $part] = match ($d->commodity->hsCode) {
            self::MOBILE => ['mobile', '188.3.ბ'],
            self::FIXED => ['fixed', '188.3.გ'],
            default => [null, null],
        };

        if ($key === null) {
            return null;
        }

        $rate = $d->rules->rate(ExciseRules::CALLS, $key);

        return $rate === null
            ? $d->noRate(new Citation($part), "call termination on a $key network")
            : $d->levied($this, $d->commodity->quantity, 'minute', $rate);
    }
}
