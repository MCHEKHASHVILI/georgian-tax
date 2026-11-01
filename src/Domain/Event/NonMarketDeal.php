<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Party;

/**
 * A company deals with a related or tax-exempt party away from the market price (98¹.4). The
 * shortfall to the company — selling below market or buying above it — is a deemed distribution.
 * Record the commercial transaction itself as a Pay or Sell; this event carries only the adjustment.
 */
final readonly class NonMarketDeal implements TaxableEvent
{
    public function __construct(
        public Party $company,
        public Party $counterparty,
        public Money $agreedPrice,
        public Money $marketPrice,
        public DateTimeImmutable $on,
        /** True when the company sells; false when it buys. */
        public bool $companySells = true,
        /** 98¹.4.ბ — a controlled cross-border transaction; the adjustment follows Chapter XVII. */
        public bool $controlledTransaction = false,
    ) {}

    /** The value that left the company. Zero when the deal favoured the company. */
    public function shortfall(): Money
    {
        $gap = $this->companySells
            ? $this->marketPrice->minus($this->agreedPrice)
            : $this->agreedPrice->minus($this->marketPrice);

        return $gap->clampedToZero();
    }

    public function recipient(): Party { return $this->company; }
    public function payer(): ?Party { return $this->company; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    public function date(): DateTimeImmutable { return $this->on; }
    public function place(): Place { return Place::georgia(); }
}
