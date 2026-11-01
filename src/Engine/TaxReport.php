<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Outcome\Collection as Collected;
use GeorgianTax\Engine\Outcome\Levied;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Outcome\Taxable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;
use Mchekhashvili\Money\Money;

/**
 * Everything every tax said about one event.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TaxReport implements Arrayable, JsonSerializable
{
    /** @var Collection<string, Outcome> keyed by tax name */
    public Collection $outcomes;

    /** @param iterable<Outcome> $outcomes */
    public function __construct(
        public TaxableEvent $event,
        iterable $outcomes = [],
    ) {
        $this->outcomes = new Collection($outcomes)->keyBy(fn (Outcome $o) => $o->tax());
    }

    public function with(Outcome $outcome): self
    {
        return new self($this->event, [...$this->outcomes->all(), $outcome->tax() => $outcome]);
    }

    public function for(string $tax): ?Outcome
    {
        return $this->outcomes->get($tax);
    }

    /** Outcomes that charge money — Taxable and Levied. @return Collection<string, Taxable|Levied> */
    public function charges(): Collection
    {
        return $this->outcomes->filter(fn (Outcome $o) => $o instanceof Taxable || $o instanceof Levied);
    }

    public function totalTax(): Money
    {
        return $this->amountOf(...$this->outcomes->keys()->all());
    }

    /** The amount the named taxes charged — e.g. the duties that join the VAT base on import. */
    public function amountOf(string ...$taxes): Money
    {
        return $this->charges()
            ->only($taxes)
            ->reduce(fn (Money $total, Taxable|Levied $o) => $total->plus($o->amount), Money::GEL(0));
    }

    /** VAT the supplier adds on top of the price — what the customer pays beyond the event amount. */
    public function vatCharged(): Money
    {
        $vat = $this->for('vat');

        return $vat instanceof Taxable && $vat->collection === Collected::MonthlyDeclaration ? $vat->amount : Money::GEL(0);
    }

    /** What the payer actually transfers to the recipient after withholding. */
    public function netToRecipient(): Money
    {
        return $this->charges()
            ->filter(fn (Outcome $o) => $o instanceof Taxable && $o->collection === Collected::WithheldAtSource)
            ->reduce(fn (Money $net, Taxable $o) => $net->minus($o->amount), $this->event->income());
    }

    /** @return array{event: string, outcomes: array<string, string>, total_tax: string} */
    public function toArray(): array
    {
        return [
            'event' => $this->event::class,
            'outcomes' => $this->outcomes->map(fn (Outcome $o) => (string) $o)->all(),
            'total_tax' => (string) $this->totalTax(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
