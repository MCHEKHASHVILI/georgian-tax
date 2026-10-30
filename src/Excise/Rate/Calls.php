<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Law\Article;

/**
 * Terminating an international call on a Georgian network, per minute (188.3): 15 tetri on
 * mobile, 8 on fixed. Describe it as a Commodity with Calls::MOBILE or Calls::FIXED as the
 * code and minutes as the quantity.
 */
#[Article('188.3', 'International call termination')]
final class Calls implements RateRule
{
    public const MOBILE = 'CALL-MOBILE';
    public const FIXED = 'CALL-FIXED';

    public function levy(Duty $d): ?Outcome
    {
        return match ($d->commodity->hsCode) {
            self::MOBILE => $d->levied($this, $d->commodity->quantity, 'minute', 0.15),
            self::FIXED => $d->levied($this, $d->commodity->quantity, 'minute', 0.08),
            default => null,
        };
    }
}
