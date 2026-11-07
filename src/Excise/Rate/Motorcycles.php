<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Excise\ExciseRules;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Motorcycles and mopeds (HS 8711), per cm³, by age — the event year minus
 * the year made (188.1 table, row 4¹: from 1.5 GEL under a year to 2.4 over 14). The rates are rows
 * of ExciseRules::MOTORCYCLES, keyed by age; '>14' is "over 14 years".
 */
#[Article('188.1', 'Motorcycles')]
final class Motorcycles implements RateRule
{
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
        $rate = $d->rules->rate(ExciseRules::MOTORCYCLES, $age > 14 ? '>14' : (string) $age);

        return $rate === null
            ? $d->noRate(new Citation('188.1'), "a motorcycle of $age years")
            : $d->levied(new Citation('188.1', 'Motorcycle'), $bike->engineCc, 'cm³', $rate, notes: ["Age $age years."]);
    }
}
