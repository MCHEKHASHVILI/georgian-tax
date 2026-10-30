<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Goods;

/**
 * Why an import may be relieved of import tax (Art. 199) and excise (Art. 194.5). One import can
 * carry one relief; each tax looks up its own article. The value limits are per the Code; the
 * once-a-day / once-a-month frequency limits are for the caller to check.
 */
enum ImportRelief
{
    case Humanitarian;            // disaster relief, humanitarian aid
    case GrantFunded;             // under a grant agreement
    case ConcessionalCredit;      // grant element ≥ 25%
    case TravellerFood;           // ≤ 500 GEL and 30 kg of food, once a day
    case PersonalGoods;           // ≤ 500 GEL and 30 kg, once in 30 days
    case TravellerAllowance;      // tobacco and alcohol within the passenger allowance
    case ReturningResident;       // ≤ 15,000 GEL after 6+ months abroad
    case Relocation;              // moving to Georgia permanently
    case PostalSmall;             // ≤ 300 GEL and 30 kg by post
    case AirTravellerGoods;       // ≤ 3,000 GEL and 30 kg by air
    case BabyOrDiabeticFood;
    case AviationOrShipSupplies;
    case OilGasOperations;
    case ConfiscatedStateProperty;
    case RawTobacco;              // until 1 January 2028
    case TemporaryAdmission;
    case CustomsProcedure;        // transit, warehousing, re-export, duty-free
    case FreeZoneProduced;
    case ReturnedGoods;
    case CisFreeTradeOrigin;      // 1994 CIS free-trade agreement
    case CustomsDutyExempt;
    case FuelInVehicleTank;
    case DiplomaticMission;
    case DisabledAdaptedCar;
    case PharmaceuticalAlcohol;

    /** Art. 199 — import tax exemption, or null if this relief doesn't cover import tax. */
    public function importTaxArticle(): ?string
    {
        return match ($this) {
            self::Humanitarian => '199.ა',
            self::GrantFunded => '199.ბ',
            self::ConcessionalCredit => '199.გ',
            self::TravellerFood => '199.დ.ა',
            self::PersonalGoods => '199.დ.ბ',
            self::TravellerAllowance => '199.დ.გ–დ.გ¹',
            self::ReturningResident => '199.დ.დ',
            self::Relocation => '199.დ.ე',
            self::PostalSmall => '199.დ.ვ',
            self::AirTravellerGoods => '199.დ.ზ',
            self::BabyOrDiabeticFood => '199.ე',
            self::AviationOrShipSupplies => '199.ვ',
            self::OilGasOperations => '199.ზ',
            self::ConfiscatedStateProperty => '199.თ',
            self::RawTobacco => '199.ი',
            self::TemporaryAdmission => '199.კ–კ²',
            self::CustomsProcedure => '199.ლ',
            self::FreeZoneProduced => '199.მ',
            self::ReturnedGoods => '199.ნ',
            self::CisFreeTradeOrigin => '199.რ',
            self::CustomsDutyExempt => '199.ს',
            default => null,
        };
    }

    /** Art. 194.5 — excise exemption (without credit), or null. */
    public function exciseArticle(): ?string
    {
        return match ($this) {
            self::TravellerAllowance => '194.5.ბ–ბ²',
            self::PostalSmall => '194.5.ბ¹',
            self::FuelInVehicleTank => '194.5.გ',
            self::AviationOrShipSupplies => '194.5.ე',
            self::OilGasOperations => '194.5.ვ',
            self::ReturningResident, self::Relocation => '194.5.თ',
            self::ReturnedGoods => '194.5.ი',
            self::DiplomaticMission => '194.5.კ',
            self::DisabledAdaptedCar => '194.5.ლ¹',
            self::PharmaceuticalAlcohol => '194.5.მ',
            self::ConfiscatedStateProperty => '194.5.ო',
            default => null,
        };
    }

    /** Customs value above which the relief stops applying, in GEL. */
    public function limitGel(): ?int
    {
        return match ($this) {
            self::TravellerFood, self::PersonalGoods => 500,
            self::PostalSmall => 300,
            self::AirTravellerGoods => 3000,
            self::ReturningResident => 15_000,
            default => null,
        };
    }

    /** Art. 199.ი — the raw tobacco relief ends on 1 January 2028. */
    public function validUntil(): ?string
    {
        return $this === self::RawTobacco ? '2028-01-01' : null;
    }
}
