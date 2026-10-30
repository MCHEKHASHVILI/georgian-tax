<?php

declare(strict_types=1);

namespace GeorgianTax\Domain;

/** GEL in tetri, so no float ever touches an amount. */
final readonly class Money
{
    private function __construct(public int $tetri) {}

    public static function gel(int|float|string $gel): self
    {
        return new self((int) round(((float) $gel) * 100));
    }

    public static function tetri(int $tetri): self
    {
        return new self($tetri);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self { return new self($this->tetri + $other->tetri); }
    public function minus(self $other): self { return new self($this->tetri - $other->tetri); }
    public function negate(): self { return new self(-$this->tetri); }
    public function times(Rate $rate): self { return new self((int) round($this->tetri * $rate->value)); }
    public function scale(float $factor): self { return new self((int) round($this->tetri * $factor)); }
    public function atLeastZero(): self { return new self(max(0, $this->tetri)); }
    public static function min(self $a, self $b): self { return $a->tetri <= $b->tetri ? $a : $b; }

    public function isZero(): bool { return $this->tetri === 0; }
    public function isPositive(): bool { return $this->tetri > 0; }
    public function greaterThan(self $other): bool { return $this->tetri > $other->tetri; }
    public function atMost(self $other): bool { return $this->tetri <= $other->tetri; }

    public function __toString(): string
    {
        return number_format($this->tetri / 100, 2, '.', '') . ' GEL';
    }
}
