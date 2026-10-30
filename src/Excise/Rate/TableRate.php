<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;

/** Everything else in the Art. 188.1 table — fuels, gases, tobacco by weight — from config/excise.php. */
#[Article('188.1', 'Excise rate table')]
final class TableRate implements RateRule
{
    public function levy(Duty $d): ?Outcome
    {
        $row = $d->table->find($d->commodity);

        return $row === null
            ? null
            : $d->levied(new Citation('188.1', $row['description']), $d->commodity->quantity, $row['unit'], $row['rate']);
    }
}
