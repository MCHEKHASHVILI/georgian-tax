<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Rate;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;

/**
 * Passenger cars (HS 8703), per cm³ of engine, by age — the event year minus the year made (188.18):
 *   up to 6 years   1.50 GEL/cm³ (188.12); hybrids with the wheel on the left 60% less (188.14)
 *   over 6 years    4.50 GEL/cm³ (188.13)
 *   right-hand drive, any age: three times the rate (188.15–16)
 *   classic car     1.00 GEL/cm³ (188.10)
 *   sports car      100 GEL flat (188.9)
 *   electric, right-hand drive: 3,000 GEL flat (188.17); left-hand: exempt (194.5.ლ)
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

        if ($car->sportsCar) {
            return $d->levied(new Citation('188.9', 'Sports car'), 1, 'car', 100);
        }

        if ($car->electric) {
            return $d->levied(new Citation('188.17', 'Right-hand-drive electric car'), 1, 'car', 3000);
        }

        if ($car->engineCc === null || ($car->yearMade === null && !$car->classicCar)) {
            return $d->notTaxable(new Citation('188.18'), 'A car needs its engine size and year made to compute excise.');
        }

        if ($car->classicCar) {
            return $d->levied(new Citation('188.10', 'Classic car'), $car->engineCc, 'cm³', 1.0);
        }

        $age = $car->ageIn($d->year());
        [$article, $rate] = $age <= 6 ? ['188.12', 1.5] : ['188.13', 4.5];
        $notes = ["Age $age years."];

        if ($car->rightHandDrive) {
            [$article, $rate] = [$age <= 6 ? '188.15' : '188.16', $rate * 3];
            $notes[] = 'Right-hand drive: three times the rate.';
        } elseif ($car->hybrid && $age <= 6) {
            [$article, $rate] = ['188.14', $rate * 0.4];
            $notes[] = 'Left-hand-drive hybrid up to 6 years: 60% less.';
        }

        return $d->levied(new Citation($article, 'Passenger car'), $car->engineCc, 'cm³', $rate, notes: $notes);
    }
}
