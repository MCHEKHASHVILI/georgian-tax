<?php

declare(strict_types=1);

namespace GeorgianTax\Excise;

/**
 * The figures the excise rate rules apply — cars (188.9–17), motorcycles by age (188.1), tobacco
 * (188.1, 188.1¹), alcoholic drinks (188¹.1), call termination (188.3) — as they stood on the day.
 * From resources/law/excise_rules.php, or the database through LawTables. A row missing on the day
 * (repealed, or not yet in force) is null, and the rule says so instead of guessing a figure.
 */
final readonly class ExciseRules
{
    public const CARS = 'excise.188.cars';
    public const MOTORCYCLES = 'excise.188.1.motorcycles';
    public const TOBACCO = 'excise.188.1.tobacco';
    public const ALCOHOL = 'excise.188¹.1';
    public const CALLS = 'excise.188.3';

    public const TABLES = [self::CARS, self::MOTORCYCLES, self::TOBACCO, self::ALCOHOL, self::CALLS];

    /** @param array<string, array<string, array<string, mixed>>> $tables table => key => values (unit, rate, percent, description) */
    public function __construct(private array $tables) {}

    public static function standard(?string $path = null): self
    {
        return new self(require $path ?? __DIR__ . '/../../resources/law/excise_rules.php');
    }

    /** The row's rate: GEL per unit, or the multiplier or reduction a '×' or '%' row sets. */
    public function rate(string $table, string $key): ?float
    {
        $rate = $this->tables[$table][$key]['rate'] ?? null;

        return $rate === null ? null : (float) $rate;
    }

    /** The row's share of the retail price, in percent (tobacco, 188.1¹). */
    public function percent(string $table, string $key): ?float
    {
        $percent = $this->tables[$table][$key]['percent'] ?? null;

        return $percent === null ? null : (float) $percent;
    }
}
