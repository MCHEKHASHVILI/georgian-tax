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
 * Goods placed under the customs procedure of temporary admission (8.48) — not an import, which is
 * release into free circulation (8.47, 160²). Import tax charges it 3% a month of the import tax
 * the goods would have paid on release on the day the declaration was registered, for each full
 * or partial calendar month in Georgia, at most that whole amount (197.4–5).
 *
 * `$goods` describes those goods as if released on that day: its `on` is the registration day, and
 * its customs value, commodity, relief (199.კ–კ² for the fully relieved cases) and euro rate are the
 * ones on that day. `$until` is the last day counted — the day the procedure ended, or, while it
 * still runs (`$ended` false), the day to count up to.
 */
final readonly class TemporaryAdmission implements TaxableEvent
{
    public function __construct(
        public Import $goods,
        public DateTimeImmutable $until,
        public bool $ended = true,
    ) {
        if ($until < $goods->on->setTime(0, 0)) {
            throw new InvalidArgumentException('Temporary admission cannot end before its declaration was registered.');
        }
    }

    /** The day the temporary admission declaration was registered. */
    public function registeredOn(): DateTimeImmutable
    {
        return $this->goods->on;
    }

    /** 197.4 — each full or partial calendar month from the registration day to `$until`. */
    public function months(): int
    {
        $from = $this->goods->on;

        return ((int) $this->until->format('Y') - (int) $from->format('Y')) * 12
            + (int) $this->until->format('n') - (int) $from->format('n') + 1;
    }

    public function recipient(): Party { return $this->goods->importer; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    public function date(): DateTimeImmutable { return $this->goods->on; }
    public function place(): Place { return Place::georgia(); }
}
