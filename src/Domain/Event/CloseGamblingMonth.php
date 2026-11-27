<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Party\Party;
use InvalidArgumentException;
use Mchekhashvili\Money\Money;

/**
 * A gambling organizer closing a calendar month of one activity — the event its profit tax looks
 * at. In that activity's part, profit tax is not on what is paid out (97.1 sets 97.9–9³ aside) but on
 * the month (135.3–4), declared and paid by the 15th of the next (153.10, 309.16):
 *
 *   online totalizator          the month's stakes, at 7% (97.9, 309.16)
 *   … foreign citizens playing  stakes less winnings paid to them, at 5% (97.9³, 98.5)
 *   gaming hall, online gambling stakes less winnings paid, at 20% (97.9¹, 98.6)
 *   … online, foreign citizens  stakes less winnings paid to them, at 5% (97.9¹, 98.5)
 *
 * One event is one activity and one group of players: `$foreignPlayers` is the part from players
 * who are foreign citizens (98.5), which only the online forms have. An organizer running several
 * activities, or with both groups of players, closes each part separately.
 *
 * The figures are the caller's: the stakes received from the players and the winnings paid to them
 * in the month. A totalizator's domestic part is taxed on its stakes alone; winnings are not deducted.
 *
 * It creates no income, so the other taxes pass it by.
 */
final readonly class CloseGamblingMonth implements TaxableEvent
{
    public function __construct(
        public Party $organizer,
        public int $year,
        public int $month,
        public GamblingActivity $activity,
        /** Stakes received from the players in the month. */
        public Money $stakes,
        /** Winnings paid to them in the month. */
        public ?Money $winnings = null,
        /** Whether these are the players who are foreign citizens (97.9³, 98.5). */
        public bool $foreignPlayers = false,
    ) {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("A month is 1 to 12, not $month.");
        }

        foreach (['stakes' => $stakes, 'winnings' => $winnings] as $name => $amount) {
            if ($amount?->isNegative()) {
                throw new InvalidArgumentException("A month's $name cannot be negative.");
            }
        }

        if ($foreignPlayers && !$activity->isOnline()) {
            throw new InvalidArgumentException('Only online gambling and totalizators have a part for foreign citizens (98.5); a gaming hall has none.');
        }
    }

    public function paidOut(): Money
    {
        return $this->winnings ?? Money::zero($this->stakes->currency);
    }

    public function recipient(): Party { return $this->organizer; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    public function date(): DateTimeImmutable { return (new DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $this->month)))->modify('last day of this month'); }
    public function place(): Place { return Place::georgia(); }
}
