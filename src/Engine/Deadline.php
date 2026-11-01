<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use DateTimeImmutable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;

/** When a tax collected one way is due under Georgian law. */
final class Deadline
{
    public static function of(Collection $collection, DateTimeImmutable $eventDate): DateTimeImmutable
    {
        $nextMonth = $eventDate->modify('first day of next month');

        return match ($collection) {
            // By the 15th of next month: withholding (154.3–154.4), monthly declarations (93.1¹,
            // 153.11–153.12), reverse-charged VAT (161, 168.4)
            Collection::WithheldAtSource, Collection::MonthlyDeclaration, Collection::ReverseCharge => $nextMonth
                ->setDate((int) $nextMonth->format('Y'), (int) $nextMonth->format('m'), 15)
                ->setTime(0, 0),
            // By 1 April of next year (153.1)
            Collection::AnnualDeclaration => new DateTimeImmutable(((int) $eventDate->format('Y') + 1) . '-04-01'),
            // Over 2 calendar years on Minister of Finance terms (153.7)
            Collection::OverTwoYears => new DateTimeImmutable(((int) $eventDate->format('Y') + 2) . '-12-31'),
            // With the import duties at customs (168.2)
            Collection::AtCustoms => $eventDate->setTime(0, 0),
            // Government-listed HS 8401–9033 imports: within 45 days (168.3)
            Collection::AtCustomsDeferred => $eventDate->modify('+45 days')->setTime(0, 0),
        };
    }
}
