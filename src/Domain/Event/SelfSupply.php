<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;

/**
 * A deemed supply with no customer: goods given to staff, kept after closing or deregistering,
 * found missing, or a building the business built for itself. The value is the purchase or
 * production cost (164.2). Creates no income, so income tax ignores it.
 */
final readonly class SelfSupply implements TaxableEvent
{
    public function __construct(
        public Party $taxpayer,
        public Money $value,
        public DateTimeImmutable $on,
        public SelfSupplyReason $reason,
        /** Input VAT was credited on these goods (160.3.გ–ე). */
        public bool $inputVatCredited = true,
    ) {}

    public function recipient(): Party { return $this->taxpayer; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::zero(); }
    public function date(): DateTimeImmutable { return $this->on; }
    public function place(): Place { return Place::georgia(); }
}
