<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Money;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Levied;
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
            if ($outcome instanceof Taxable || $outcome instanceof Levied) {
                $total = $total->plus($outcome->amount);
            }
        }

        return $total;
    }

    /** The amount the named taxes levied — e.g. the duties that join the VAT base on import. */
    public function amountOf(string ...$taxes): Money
    {
        $total = Money::zero();

        foreach ($this->outcomes as $outcome) {
            if (in_array($outcome->tax(), $taxes, true) && ($outcome instanceof Taxable || $outcome instanceof Levied)) {
                $total = $total->plus($outcome->amount);
            }
        }

        return $total;
    }

    /** VAT the supplier adds on top of the price — what the customer pays beyond the event amount. */
    public function vatCharged(): Money
    {
        $outcome = $this->for('vat');

        return $outcome instanceof Taxable && $outcome->collection === Collection::MonthlyDeclaration
            ? $outcome->amount
            : Money::zero();
    }

    /** What the payer actually transfers to the recipient after withholding. */
    public function netToRecipient(): Money
    {
        $net = $this->event->income();

        foreach ($this->outcomes as $outcome) {
            if ($outcome instanceof Taxable && $outcome->collection === Collection::WithheldAtSource) {
                $net = $net->minus($outcome->amount);
            }
        }

        return $net;
    }
}
