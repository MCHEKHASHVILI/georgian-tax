<?php

declare(strict_types=1);

namespace GeorgianTax\Engine\Outcome;

use GeorgianTax\Law\Citation;

/** Outside the tax altogether — e.g. a company's income is profit tax, not income tax. */
final readonly class NotTaxable implements Outcome
{
    public function __construct(
        public string $taxName,
        public Citation $citation,
        public string $reason,
    ) {}

    public function tax(): string { return $this->taxName; }
    public function basis(): Citation { return $this->citation; }
    public function reversed(float $share): self { return $this; }

    public function __toString(): string
    {
        return "Not taxable — {$this->citation}: {$this->reason}";
    }
}
