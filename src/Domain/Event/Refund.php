<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;

/**
 * Reverses all or part of an earlier event. Taxes assess the original and negate the result
 * in proportion, so no rule needs refund-specific logic.
 */
final readonly class Refund implements TaxableEvent
{
    public function __construct(
        public TaxableEvent $original,
        public DateTimeImmutable $on,
        public ?Money $portion = null,
    ) {}

    /** Share of the original being reversed, 0–1. */
    public function share(): float
    {
        $original = $this->original->income();

        if ($this->portion === null || $original->isZero()) {
            return 1.0;
        }

        return min(1.0, $this->portion->tetri / $original->tetri);
    }

    public function recipient(): Party { return $this->original->recipient(); }
    public function payer(): ?Party { return $this->original->payer(); }
    public function kind(): IncomeKind { return $this->original->kind(); }
    public function income(): Money { return $this->original->income()->scale($this->share())->negate(); }
    public function date(): DateTimeImmutable { return $this->on; }
    public function place(): Place { return $this->original->place(); }
}
