<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Property;

/** What a property item is, as Art. 200–204 classify it. */
enum PropertyKind
{
    // Taxable property (201)
    case Building;                 // real estate — buildings, structures, their parts
    case UnfinishedConstruction;
    case PassengerCar;             // HS 8703
    case Yacht;                    // yachts and boats, HS 8903
    case Helicopter;
    case Aircraft;
    case Equipment;                // fixed assets other than real estate, uninstalled equipment

    // Land (200.2–4, 203–204)
    case Arable;                   // incl. perennial plantations
    case Homestead;
    case Hayfield;
    case Pasture;
    case NonAgriculturalLand;
    case NaturalResourceLand;      // allotted under a natural-resource licence (204.3)

    public function isLand(): bool
    {
        return in_array($this, [self::Arable, self::Homestead, self::Hayfield, self::Pasture, self::NonAgriculturalLand, self::NaturalResourceLand], true);
    }

    public function isAgriculturalLand(): bool
    {
        return in_array($this, [self::Arable, self::Homestead, self::Hayfield, self::Pasture], true);
    }

    public function isRealEstate(): bool
    {
        return $this === self::Building || $this === self::UnfinishedConstruction;
    }

    /** What an individual pays property tax on, outside economic activity (201.1.გ.ა). */
    public function isPersonalTaxable(): bool
    {
        return $this->isRealEstate() || in_array($this, [self::PassengerCar, self::Yacht, self::Helicopter, self::Aircraft], true);
    }
}
