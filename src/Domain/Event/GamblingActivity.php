<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

/**
 * The gambling activities profit tax looks at month by month rather than on distribution
 * (97.9–9³, 98.5–6, 309.16).
 */
enum GamblingActivity
{
    /** სისტემურ-ელექტრონული ფორმით ტოტალიზატორის თამაშობები — 97.9 (309.16), 97.9³ */
    case OnlineTotalizator;
    /** სათამაშო აპარატების სალონი — 97.9¹ */
    case GamingHall;
    /** სისტემურ-ელექტრონული ფორმით აზარტული თამაშობები — 97.9¹ */
    case OnlineGambling;

    /** 98.5 and 97.9³ give a part of their own to foreign citizens playing online; a gaming hall has none. */
    public function isOnline(): bool
    {
        return $this !== self::GamingHall;
    }
}
