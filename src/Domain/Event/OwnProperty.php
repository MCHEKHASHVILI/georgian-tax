<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Property\PropertyItem;

/**
 * Holding a property item or land plot during a tax year — the annual event property tax looks at
 * (Art. 201, 203). It creates no income, so the other taxes pass it by.
 */
final readonly class OwnProperty implements TaxableEvent
{
    public function __construct(
        public Party $owner,
        public PropertyItem $item,
        public int $year,
        /** 202.9, 205.8 — months held (individual) or months the company existed in the year. */
        public int $months = 12,
        /** 206.1.ა — the owner's family income in the year before; individuals only. */
        public ?Money $familyIncome = null,
    ) {}

    public function recipient(): Party { return $this->owner; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::zero(); }
    public function date(): DateTimeImmutable { return new DateTimeImmutable("{$this->year}-12-31"); }
    public function place(): Place { return Place::georgia(); }
}
