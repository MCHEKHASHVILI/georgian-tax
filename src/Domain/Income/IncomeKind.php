<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Income;

/**
 * What the money is for. Art. 14: everything that isn't goods is a service, so service-like kinds
 * (rent, transport, telecom) report isService().
 */
enum IncomeKind
{
    // Employment — Art. 12
    case Salary;

    // Economic activity — Art. 102
    case ServiceFee;
    case GoodsSupply;
    case Rent;
    case ResidentialRent;
    case Leasing;
    case InternationalTransport;
    case InternationalTelecom;
    case OilGasSubcontract;
    case Insurance;
    case TouristHotelPayout;

    // Passive — Art. 8.12, 8.19, 8.21
    case Dividend;
    case Interest;
    case Royalty;

    // Disposals — produced by Sell
    case AssetGain;
    case SecuritiesGainViaBroker;

    // Transfers — produced by Gift / Inherit
    case Gift;
    case Inheritance;

    // Pensions and scholarships
    case Pension;
    case StatePension;
    case AccumulativePensionPayout;
    case AccumulativePensionRefund;
    case PrivatePensionPayout;
    case PrivatePensionEarlyWithdrawal;
    case PrivatePensionContribution;
    case Scholarship;
    case StateScholarship;

    // Winnings — Art. 80.8, 154.1.ე
    case LotteryWin;
    case PromoDrawWin;
    case GamingAccountWithdrawal;

    // Exempt by nature — Art. 82.1, 103.1
    case Grant;
    case StateCompensation;
    case BudgetAid;
    case CharityFromStateNonProfit;
    case CharityForMedicalTreatment;
    case CharityToVulnerable;
    case SportsAward;
    case Alimony;
    case DivorceSettlement;
    case BloodDonation;
    case PrivatizationBenefit;
    case LandOwnershipRecognition;
    case DisplacedPersonCompensation;
    case ServiceInjuryAid;
    case PartnershipDistribution;
    case LiquidationRealEstate;
    case RealEstateFromAdministrativeEmployer;
    case FuelFromAdministrativeBody;
    case FreeGuaranteeOrServitude;
    case FreeHotelStay;
    case NotaryChamberAid;
    case ExpropriationCompensation;
    case MicroPowerSurplus;
    case InsuranceIndemnity;
    case PartnerContribution;

    case Other;

    public function isService(): bool
    {
        return in_array($this, [
            self::ServiceFee, self::Rent, self::ResidentialRent, self::Leasing,
            self::InternationalTransport, self::InternationalTelecom,
            self::OilGasSubcontract, self::Insurance,
        ], true);
    }

    /** For VAT: the payment is consideration for a supply of goods or services (Art. 159). */
    public function isSupply(): bool
    {
        return $this->isBusinessIncome() || in_array($this, [self::Royalty, self::Interest], true);
    }

    /** Income a special regime taxes — Art. 86, 90.3 (salary is excluded), 95⁵. */
    public function isBusinessIncome(): bool
    {
        return $this->isService() || in_array($this, [self::GoodsSupply, self::TouristHotelPayout], true);
    }
}
