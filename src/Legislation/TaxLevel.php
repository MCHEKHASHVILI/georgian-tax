<?php

declare(strict_types=1);

namespace GeorgianTax\Legislation;

/**
 * Art. 6.2 — who levies a tax. National taxes apply across Georgia (6.3); a local tax is set by the
 * Code but introduced by a municipal council, within the Code's caps, inside that municipality (6.4).
 */
enum TaxLevel: string
{
    case National = 'national';
    case Local = 'local';

    public function label(): string
    {
        return match ($this) {
            self::National => 'საერთო-სახელმწიფოებრივი',
            self::Local => 'ადგილობრივი',
        };
    }
}
