<?php

declare(strict_types=1);

namespace GeorgianTax\Engine\Outcome;

use DateTimeImmutable;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Rate;
use GeorgianTax\Law\Citation;

final readonly class Taxable implements Outcome
{
    /** @param list<string> $notes */
    public function __construct(
        public string $taxName,
        public Citation $citation,
        public Party $taxpayer,
        public Money $base,
        public Rate $rate,
        public Money $amount,
        public Collection $collection,
        public ?Party $agent,
        public DateTimeImmutable $due,
        public array $notes = [],
    ) {}

    public function tax(): string { return $this->taxName; }
    public function basis(): Citation { return $this->citation; }

    public function withBase(Money $base, string $note): self
    {
        return new self(
            $this->taxName, $this->citation, $this->taxpayer, $base, $this->rate,
            $base->times($this->rate), $this->collection, $this->agent, $this->due,
            [...$this->notes, $note],
        );
    }

    public function reversed(float $share): self
    {
        return new self(
            $this->taxName, $this->citation, $this->taxpayer,
            $this->base->scale($share)->negate(), $this->rate, $this->amount->scale($share)->negate(),
            $this->collection, $this->agent, $this->due,
            [...$this->notes, 'Reversal of an earlier payment.'],
        );
    }

    public function __toString(): string
    {
        $who = $this->agent ? "withheld by {$this->agent->id}" : "paid by {$this->taxpayer->id}";

        return "{$this->citation}: {$this->rate} of {$this->base} = {$this->amount}, $who, due {$this->due->format('Y-m-d')}";
    }
}
