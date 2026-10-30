<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Money;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Outcome\Taxable;

/** Everything every tax said about one event. */
final readonly class TaxReport
{
    /** @param list<Outcome> $outcomes */
    public function __construct(
        public TaxableEvent $event,
        public array $outcomes = [],
    ) {}

    public function with(Outcome $outcome): self
    {
        return new self($this->event, [...$this->outcomes, $outcome]);
    }

    public function for(string $tax): ?Outcome
    {
        foreach ($this->outcomes as $outcome) {
            if ($outcome->tax() === $tax) {
                return $outcome;
            }
        }

        return null;
    }

    public function totalTax(): Money
    {
        $total = Money::zero();

        foreach ($this->outcomes as $outcome) {
            if ($outcome instanceof Taxable) {
                $total = $total->plus($outcome->amount);
            }
        }

        return $total;
    }

    /** What the payer actually transfers to the recipient after withholding. */
    public function netToRecipient(): Money
    {
        $net = $this->event->income();

        foreach ($this->outcomes as $outcome) {
            if ($outcome instanceof Taxable && $outcome->agent !== null) {
                $net = $net->minus($outcome->amount);
            }
        }

        return $net;
    }
}
