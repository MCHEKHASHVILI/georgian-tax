<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax;

/**
 * The limits the Code sets on the councils' property tax rates, as they stood on a day:
 *
 *   202.1    company or organization property   up to 1% of value
 *   202.3    property leased out                up to 1%
 *   202.3¹   a leasing company's leased property up to 0.6%
 *   202.5    an individual's property           0.8% – 1%
 *   204.1.დ  agricultural land                  up to 150% of the base rate (a multiplier of 1.5)
 *   204.2.ბ  non-agricultural land              territorial coefficient up to 1.5
 *
 * and, for land, the 204.3 natural-resource rate, up to 3 GEL a hectare, which PropertyTax adds
 * from the land rates. From the 'caps' of resources/law/property.php, or the database (table
 * `property.caps`) through LawTables. A limit not in force on the day is null.
 */
final readonly class PropertyCaps
{
    public const TABLE = 'property.caps';

    /** @param array<string, array{unit?: string, min?: float|int, max?: float|int}> $caps article => limits */
    public function __construct(private array $caps) {}

    public static function standard(?string $path = null): self
    {
        $default = __DIR__ . '/../../resources/law/property.php';

        // A copy of property.php published before the caps were added still has its land rates; take the caps from the package.
        return new self((require $path ?? $default)['caps'] ?? (require $default)['caps']);
    }

    public function max(string $article): ?float
    {
        return isset($this->caps[$article]['max']) ? (float) $this->caps[$article]['max'] : null;
    }

    public function min(string $article): ?float
    {
        return isset($this->caps[$article]['min']) ? (float) $this->caps[$article]['min'] : null;
    }

    /** '%' of value, '×' the base rate, or a rate's own unit ('GEL/ha'). */
    public function unit(string $article): string
    {
        return (string) ($this->caps[$article]['unit'] ?? '');
    }

    /** @param array{unit?: string, min?: float|int, max?: float|int} $limits */
    public function with(string $article, array $limits): self
    {
        return new self([...$this->caps, $article => $limits]);
    }
}
