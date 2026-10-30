<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Property;

use GeorgianTax\Domain\Money;

/**
 * One item of property or one land plot.
 *
 * The value means what the Code says it means for the owner:
 *   company        average annual net book value — mean of the values at the start and end of the year (202.1)
 *   leased out     book value when first leased, then the residual value it would have had (202.3)
 *   individual     market value at the end of the previous year (202.5)
 * Land is taxed by area instead: hectares for agricultural and natural-resource land, m² otherwise.
 */
final readonly class PropertyItem
{
    public function __construct(
        public PropertyKind $kind,
        public ?Money $value = null,
        /** Land area: hectares (agricultural, natural-resource), square metres (non-agricultural). */
        public ?float $area = null,
        /** Municipality, in English — 'Tbilisi', 'Marneuli', 'Kazbegi' — for land rates (204). */
        public ?string $municipality = null,
        /** 202.1 — year acquired, for revaluing a company's real estate; null means unknown. */
        public ?int $acquiredYear = null,
        /** 202.2.ა — the company revalues its real estate and has audited statements (valid 4 years). */
        public bool $revaluedWithAudit = false,
        /** Used in economic activity — decides 201.1.გ.გ for individuals and 206.1.ე for organizations. */
        public bool $usedInBusiness = false,
        /** Leased out (lease, rent, usufruct): 202.3, and 206.2 takes most exemptions away. */
        public bool $leasedOut = false,
        /** 202.3¹ — leased out by a leasing company: 0.6% of the initial book value. */
        public bool $byLeasingCompany = false,
        public ?PropertyExemption $exemption = null,
    ) {}

    public static function land(PropertyKind $kind, float $area, ?string $municipality = null, ?PropertyExemption $exemption = null, bool $leasedOut = false): self
    {
        return new self($kind, area: $area, municipality: $municipality, leasedOut: $leasedOut, exemption: $exemption);
    }
}
