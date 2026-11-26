<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use GeorgianTax\Domain\Period;

/**
 * The lengths of time the engine applies, as they stood on a day:
 *
 *   income   82.1.ვ.ა   a dwelling with its land held more than 2 years
 *            82.1.ვ.ბ   a car held more than 6 months from registration
 *            82.1.ვ.გ   any other asset held more than 2 years
 *            82.1.ვ.გ.note  business use ignored once 2 years have passed since it ended
 *   excise   189.8      a car or motorcycle exported within 180 calendar days of its release
 *   import   198.3      a car exported within 180 calendar days of its release
 *
 * From resources/law/periods.php, or the database (tables `income.periods`, `excise.periods`,
 * `import.periods`) through LawTables. A period not in force on the day is null, and the rule
 * says so instead of guessing one.
 */
final readonly class Periods
{
    /** tax => table */
    public const TABLES = [
        'income' => 'income.periods',
        'excise' => 'excise.periods',
        'import' => 'import.periods',
    ];

    /** @param array<string, array<string, array<string, mixed>>> $tables table => key => values (length, unit, description) */
    public function __construct(private array $tables) {}

    public static function standard(?string $path = null): self
    {
        return new self(require $path ?? __DIR__ . '/../../resources/law/periods.php');
    }

    /** The period `$key` of a tax's table (its article), or null when none is in force. */
    public function of(string $tax, string $key): ?Period
    {
        $row = $this->tables[self::TABLES[$tax] ?? "$tax.periods"][$key] ?? null;

        return $row === null ? null : Period::fromRow($row);
    }
}
