<?php

declare(strict_types=1);

namespace GeorgianTax\Domain;

use InvalidArgumentException;

final readonly class Rate
{
    private function __construct(public float $value)
    {
        if ($value < 0 || $value > 1) {
            throw new InvalidArgumentException("Rate must be between 0 and 1, got $value.");
        }
    }

    public static function percent(float $percent): self
    {
        return new self($percent / 100);
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    public function __toString(): string
    {
        return rtrim(rtrim(number_format($this->value * 100, 2, '.', ''), '0'), '.') . '%';
    }
}
