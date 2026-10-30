<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Rule;

use GeorgianTax\Domain\Money;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\ImportTax\Clearance;
use GeorgianTax\Law\Article;

/**
 * Passenger cars (HS 8703): 0.05 GEL per cm³ of engine, plus 5% of that amount for each year the
 * car has been in use (197.6) — counted as the declaration year minus the year made.
 */
#[Article('197.6', 'Passenger car import tax')]
final class PassengerCar implements Rule
{
    public function assess(Clearance $c): ?Outcome
    {
        $car = $c->commodity;

        if (!$car->isPassengerCar()) {
            return null;
        }

        if ($car->engineCc === null || $car->yearMade === null) {
            return $c->unresolved($this, 'A car needs its engine size and year made to compute import tax.');
        }

        $base = $car->engineCc * 0.05;
        $years = $car->ageIn($c->year());
        $surcharge = Money::gel($base * 0.05 * $years);

        return $c->levied($this, $car->engineCc, 'cm³', 0.05, $surcharge, [
            "Plus 5% for each of $years years in use: $surcharge.",
        ]);
    }
}
