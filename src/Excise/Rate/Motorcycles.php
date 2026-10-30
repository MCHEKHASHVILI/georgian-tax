<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;

/** Motorcycles and mopeds (HS 8711), per cm³, by age (188.1 table, row 4). */
#[Article('188.1', 'Motorcycles')]
final class Motorcycles implements RateRule
{
    /** Age in years => GEL per cm³; 15 means "over 14". */
    private const RATES = [0 => 1.5, 1 => 1.3, 2 => 0.7, 3 => 0.7, 4 => 0.7, 5 => 0.7, 6 => 0.8, 7 => 0.8,
        8 => 0.8, 9 => 0.8, 10 => 1.0, 11 => 1.3, 12 => 1.5, 13 => 1.8, 14 => 2.1, 15 => 2.4];

    public function levy(Duty $d): ?Outcome
    {
        $bike = $d->commodity;

        if (!$bike->isMotorcycle()) {
            return null;
        }

        if ($bike->engineCc === null || $bike->yearMade === null) {
            return $d->notTaxable(new Citation('188.1'), 'A motorcycle needs its engine size and year made to compute excise.');
        }

        $age = $bike->ageIn($d->year());

        return $d->levied(new Citation('188.1', 'Motorcycle'), $bike->engineCc, 'cm³', self::RATES[min($age, 15)], notes: ["Age $age years."]);
    }
}
