<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax;

use InvalidArgumentException;

/**
 * Property tax is local: the Code sets caps and base rates, and each municipal council (sakrebulo)
 * sets the rate it charges. This holds those decisions, checked against the Code's limits. The
 * defaults are the caps for property and the base rates for land.
 *
 *   company property      up to 1%        (202.1, 202.3)
 *   leasing company       up to 0.6%      (202.3¹)
 *   individual property   0.8% – 1%       (202.5)
 *   agricultural land     base × up to 1.5    (204.1.დ)
 *   non-agricultural land 0.24 GEL/m² × territorial coefficient up to 1.5 (204.2)
 *   natural-resource land up to 3 GEL/ha  (204.3)
 */
final readonly class MunicipalRates
{
    /**
     * @param array<string, float> $landMultipliers       municipality => multiplier on the 204.1 base
     * @param array<string, float> $territorialCoefficients municipality => coefficient on 204.2
     */
    public function __construct(
        public float $companyPercent = 1.0,
        public float $leasingCompanyPercent = 0.6,
        public float $individualPercent = 1.0,
        private array $landMultipliers = [],
        private array $territorialCoefficients = [],
        public float $naturalResourcePerHa = 3.0,
    ) {
        self::check($companyPercent, 0, 1, '202.1 company rate');
        self::check($leasingCompanyPercent, 0, 0.6, '202.3¹ leasing company rate');
        self::check($individualPercent, 0.8, 1, '202.5 individual rate');
        self::check($naturalResourcePerHa, 0, 3, '204.3 natural-resource rate');

        foreach ($landMultipliers as $m => $x) {
            self::check($x, 0, 1.5, "204.1.დ multiplier for $m");
        }

        foreach ($territorialCoefficients as $m => $x) {
            self::check($x, 0, 1.5, "204.2.ბ coefficient for $m");
        }
    }

    public function landMultiplier(?string $municipality): float
    {
        return $this->landMultipliers[self::key($municipality)] ?? 1.0;
    }

    public function territorialCoefficient(?string $municipality): float
    {
        return $this->territorialCoefficients[self::key($municipality)] ?? 1.0;
    }

    private static function key(?string $municipality): string
    {
        return strtolower((string) $municipality);
    }

    private static function check(float $value, float $min, float $max, string $what): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("$what must be between $min and $max, got $value.");
        }
    }
}
