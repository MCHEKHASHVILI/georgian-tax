<?php

declare(strict_types=1);

namespace GeorgianTax\Engine\Outcome;

use DateTimeImmutable;

/** How the tax reaches the budget, and by when. */
enum Collection
{
    /** The payer withholds on payment and declares by the 15th of next month (Art. 154.3–154.4). */
    case WithheldAtSource;

    /** The taxpayer declares and pays by the 15th of next month (Art. 93.1¹, 153.11, 153.12). */
    case MonthlyDeclaration;

    /** The taxpayer declares by 1 April of next year (Art. 153.1). */
    case AnnualDeclaration;

    /** Declared and paid over 2 calendar years on Minister of Finance terms (Art. 153.7). */
    case OverTwoYears;

    /** VAT: the Georgian customer charges itself and pays by the 15th of next month (Art. 161, 168.4). */
    case ReverseCharge;

    /** VAT on import: paid with import duties at customs (Art. 168.2). */
    case AtCustoms;

    /** VAT on Government-listed HS 8401–9033 imports: within 45 days of release (Art. 168.3). */
    case AtCustomsDeferred;

    public function deadline(DateTimeImmutable $eventDate): DateTimeImmutable
    {
        $nextMonth = $eventDate->modify('first day of next month');

        return match ($this) {
            self::WithheldAtSource, self::MonthlyDeclaration, self::ReverseCharge => $nextMonth
                ->setDate((int) $nextMonth->format('Y'), (int) $nextMonth->format('m'), 15)
                ->setTime(0, 0),
            self::AnnualDeclaration => new DateTimeImmutable(((int) $eventDate->format('Y') + 1) . '-04-01'),
            self::OverTwoYears => new DateTimeImmutable(((int) $eventDate->format('Y') + 2) . '-12-31'),
            self::AtCustoms => $eventDate->setTime(0, 0),
            self::AtCustomsDeferred => $eventDate->modify('+45 days')->setTime(0, 0),
        };
    }
}
