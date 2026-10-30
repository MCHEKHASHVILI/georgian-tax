<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Party;

use GeorgianTax\Law\Article;
use GeorgianTax\Law\Cites;

/**
 * An individual is resident for the whole tax year if physically in Georgia 183+ days in any continuous
 * 12-month period ending in that year, or abroad in Georgian state service that year.
 * Days of treatment, holiday, business trips or study abroad count as days in Georgia (34.3).
 * Diplomats, international-organization staff, transit and treatment in Georgia do not (34.4).
 */
#[Article('34', 'Resident and non-resident individuals')]
final class ResidencyResolver
{
    use Cites;

    public function resolve(int $countableDaysInRolling12Months, bool $inGeorgianStateServiceAbroad = false): Residency
    {
        return $countableDaysInRolling12Months >= 183 || $inGeorgianStateServiceAbroad
            ? Residency::Resident
            : Residency::NonResident;
    }
}
