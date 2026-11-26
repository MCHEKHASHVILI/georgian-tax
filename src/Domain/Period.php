<?php

declare(strict_types=1);

namespace GeorgianTax\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Stringable;

/** A length of time the Code sets: so many calendar days, months or years. */
final readonly class Period implements Stringable
{
    public const UNITS = ['days', 'months', 'years'];

    public function __construct(public int $length, public string $unit)
    {
        if ($length < 0) {
            throw new InvalidArgumentException('A period cannot be negative.');
        }

        if (!in_array($unit, self::UNITS, true)) {
            throw new InvalidArgumentException("A period is counted in days, months or years, not '$unit'.");
        }
    }

    public static function days(int $length): self
    {
        return new self($length, 'days');
    }

    /** @param array{length: int|string, unit: string} $row */
    public static function fromRow(array $row): self
    {
        return new self((int) $row['length'], (string) $row['unit']);
    }

    /** The day this period after `$from` — the period counted from the next day (3.2). */
    public function after(DateTimeImmutable $from): DateTimeImmutable
    {
        return $from->setTime(0, 0)->modify("+$this");
    }

    /** "180 calendar days", "6 months", "1 year" — as the Code says it. */
    public function describe(): string
    {
        return $this->unit === 'days' ? "$this->length calendar " . ($this->length === 1 ? 'day' : 'days') : (string) $this;
    }

    /** "180 days", "6 months", "1 year" — as DateTimeImmutable::modify() takes it. */
    public function __toString(): string
    {
        return $this->length . ' ' . ($this->length === 1 ? rtrim($this->unit, 's') : $this->unit);
    }
}
