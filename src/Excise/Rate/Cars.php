<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Excise\ExciseRules;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Passenger cars (HS 8703), per cm³ of engine, by age — the event year minus the year made (188.18):
 *   up to 6 years   1.50 GEL/cm³ (188.12); hybrids with the wheel on the left 60% less (188.14)
 *   over 6 years    4.50 GEL/cm³ (188.13)
 *   right-hand drive, any age: three times the rate (188.15–16)
 *   classic car     1.00 GEL/cm³ (188.10)
 *   sports car      100 GEL flat (188.9)
 *   electric, right-hand drive: 3,000 GEL flat (188.17); left-hand: exempt (194.5.ლ)
 * The figures are rows of ExciseRules::CARS, keyed by the part that sets them.
 */
#[Article('188.9–18', 'Passenger cars')]
final class Cars implements RateRule
{
    public function levy(Duty $d): ?Outcome
    {
        $car = $d->commodity;

        if (!$car->isPassengerCar()) {
            return null;
        }

        $rate = static fn (string $part): ?float => $d->rules->rate(ExciseRules::CARS, $part);

        if ($car->sportsCar) {
            return self::flat($d, '188.9', 'Sports car', $rate('188.9'));
        }

        if ($car->electric) {
            return self::flat($d, '188.17', 'Right-hand-drive electric car', $rate('188.17'));
        }

        if ($car->engineCc === null || ($car->yearMade === null && !$car->classicCar)) {
            return $d->notTaxable(new Citation('188.18'), 'A car needs its engine size and year made to compute excise.');
        }

        if ($car->classicCar) {
            $classic = $rate('188.10');

            return $classic === null
                ? $d->noRate(new Citation('188.10'), 'a classic car')
                : $d->levied(new Citation('188.10', 'Classic car'), $car->engineCc, 'cm³', $classic);
        }

        $age = $car->ageIn($d->year());
        $article = $age <= 6 ? '188.12' : '188.13';
        $base = $rate($article);
        $notes = ["Age $age years."];

        if ($base === null) {
            return $d->noRate(new Citation($article), 'a passenger car');
        }

        // The right-hand-drive multiplier and the hybrid reduction modify the base; with either part
        // not in force on the day, the base rate stands.
        $times = $car->rightHandDrive ? $rate($age <= 6 ? '188.15' : '188.16') : null;
        $less = !$car->rightHandDrive && $car->hybrid && $age <= 6 ? $rate('188.14') : null;

        if ($times !== null) {
            $article = $age <= 6 ? '188.15' : '188.16';
            $base *= $times;
            $notes[] = "Right-hand drive: $times times the rate.";
        } elseif ($less !== null) {
            $article = '188.14';
            $base *= (100 - $less) / 100;
            $notes[] = "Left-hand-drive hybrid up to 6 years: $less% less.";
        }

        return $d->levied(new Citation($article, 'Passenger car'), $car->engineCc, 'cm³', $base, notes: $notes);
    }

    private static function flat(Duty $d, string $article, string $label, ?float $gel): Outcome
    {
        return $gel === null
            ? $d->noRate(new Citation($article), strtolower($label))
            : $d->levied(new Citation($article, $label), 1, 'car', $gel);
    }
}
