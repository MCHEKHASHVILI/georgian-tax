<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax;

use InvalidArgumentException;

/**
 * Property tax is local: the Code sets caps and base rates, and each municipal council (sakrebulo)
 * sets the rate it charges. This holds those decisions. A rate left null is the Code's cap (for
 * land, the base rate). The caps themselves are law (PropertyCaps): each decision is checked
 * against the cap in force on the day the rate is fixed — 31 December of the tax year for property
 * (202.8), 1 April for land (204.4) — and one outside it leaves the assessment unresolved.
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
        public ?float $companyPercent = null,
        public ?float $leasingCompanyPercent = null,
        public ?float $individualPercent = null,
        private array $landMultipliers = [],
        private array $territorialCoefficients = [],
        public ?float $naturalResourcePerHa = null,
    ) {
        foreach (['company rate' => $companyPercent, 'leasing company rate' => $leasingCompanyPercent,
            'individual rate' => $individualPercent, 'natural-resource rate' => $naturalResourcePerHa] as $what => $value) {
            self::check($value, $what);
        }

        foreach ($landMultipliers as $m => $x) {
            self::check($x, "land multiplier for $m");
        }

        foreach ($territorialCoefficients as $m => $x) {
            self::check($x, "territorial coefficient for $m");
        }
    }

    /** The council's multiplier on the 204.1 base, or null when it set none (the base applies). */
    public function landMultiplier(?string $municipality): ?float
    {
        return $this->landMultipliers[self::key($municipality)] ?? null;
    }

    /** The council's 204.2.ბ coefficient, or null when it set none (the base applies). */
    public function territorialCoefficient(?string $municipality): ?float
    {
        return $this->territorialCoefficients[self::key($municipality)] ?? null;
    }

    private static function key(?string $municipality): string
    {
        return strtolower((string) $municipality);
    }

    /** Only what no law could allow; the caps are checked on the day, against the law then in force. */
    private static function check(?float $value, string $what): void
    {
        if ($value !== null && (!is_finite($value) || $value < 0)) {
            throw new InvalidArgumentException("The council's $what must be a non-negative number, got $value.");
        }
    }
}
