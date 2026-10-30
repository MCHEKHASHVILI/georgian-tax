<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Property;

/**
 * Art. 206.1 exemptions that turn on facts the engine cannot see — what the land is used for, a
 * certificate from the municipality, who financed the works. The caller asserts one; the engine
 * cites it and checks its date limits and the 206.2 leasing carve-out.
 *
 * Exemptions the engine decides itself — family income (ა), organizations (ე), free industrial
 * zone (ჩ), yachts (ჰ3), bankrupts (ჰ4), high-mountain enterprises (ჰ6) — are not listed here.
 */
enum PropertyExemption: string
{
    case RoadsAndPowerLines = '206.1.დ';
    case OilAndGasProperty = '206.1.ვ';
    case StateLandForBudgetBody = '206.1.ზ';
    case MonumentLand = '206.1.თ';
    case ParksAndProtectedAreas = '206.1.ი';
    case Windbreak = '206.1.ი¹';
    case ReservoirsAndTransport = '206.1.კ';
    case WaterSupplyReservoirs = '206.1.ლ';
    case OilAndGasLand = '206.1.მ';
    case DisasterDamagedSoil = '206.1.ნ';
    case OccupiedTerritory = '206.1.ო';
    case NearOccupiedTerritory = '206.1.ო¹';
    case HousingDisplacedPersons = '206.1.ო²';
    case UnusedStateAgriculturalLand = '206.1.პ';
    case AirportsAndPorts = '206.1.ჟ';
    case NewlyDevelopedLand = '206.1.რ';       // 5 years from receipt
    case ResettledFamily = '206.1.ს';          // 5 years from settlement
    case SmallFarmOwnedSince2004 = '206.1.ტ';  // up to 5 ha, owned on 1 March 2004
    case SmallFarmOfDisplacedPerson = '206.1.ტ¹';
    case IrrigationInfrastructure = '206.1.უ';
    case WarDisabledPrivatizedLand = '206.1.ფ';
    case HighMountainResident = '206.1.ქ';
    case ResearchLand = '206.1.ღ';
    case SocialCareLand = '206.1.ყ';
    case HarvestDestroyed = '206.1.შ';
    case BiologicalAsset = '206.1.ც';
    case LeasedFromResident = '206.1.ძ';
    case MedicalProperty = '206.1.წ';
    case MedicalLand = '206.1.ჭ';
    case TouristZoneHotel = '206.1.ხ';         // until 1 January 2026
    case FarmerMovables = '206.1.ჯ';
    case AgriculturalCooperative = '206.1.ჯ¹'; // until 1 January 2028
    case PublicSchool = '206.1.ჰ';
    case ApartmentBuildingLand = '206.1.ჰ¹';
    case HouseOrGarageLand = '206.1.ჰ²';       // within the municipal area limit
    case StateUniversity = '206.1.ჰ⁵';

    public function validUntil(): ?string
    {
        return match ($this) {
            self::TouristZoneHotel => '2026-01-01',
            self::AgriculturalCooperative => '2028-01-01',
            default => null,
        };
    }

    /** 206.2 — a state university keeps its exemption on property it leases out for education. */
    public function survivesLeasing(): bool
    {
        return $this === self::StateUniversity;
    }
}
