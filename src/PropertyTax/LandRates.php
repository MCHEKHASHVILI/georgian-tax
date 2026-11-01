<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax;

/** The Art. 204 land rates: per-hectare bases by municipality, and the m² and natural-resource rates. */
final readonly class LandRates
{
    /** @param array{arable: array<string, float>, hayfield: array<string, float>, pasture: array<string, float>, non_agricultural_per_m2: float, natural_resource_per_ha: float} $table */
    public function __construct(public array $table) {}

    public static function standard(?string $path = null): self
    {
        return new self(require $path ?? __DIR__ . '/../../resources/law/property.php');
    }
}
