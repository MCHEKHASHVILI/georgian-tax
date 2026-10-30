<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Income;

/**
 * What is being supplied, in the terms VAT exemptions use (Art. 170–173). Leave it null for an
 * ordinary supply; VAT derives the obvious ones (interest → Financial) from the IncomeKind.
 * The VAT exemption table maps each case to its article.
 */
enum SupplyCategory
{
    // Exempt without credit — Art. 170
    case MedicalCare;
    case PatientTransport;
    case Education;
    case PrivateTutoring;
    case ArtsSportsForMinors;
    case SocialCare;
    case ChildProtection;
    case MembershipServices;
    case HeritageRestoration;
    case UniversalPostal;
    case PostageStamps;
    case Funeral;
    case DiabeticBread;
    case PatriarchateReligiousItems;
    case ChurchRestoration;

    // Exempt without credit — Art. 171
    case Financial;
    case Gambling;
    case Land;
    case SeizedHomeReturn;
    case EducationalMedia;
    case Periodicals;
    case StateProperty;
    case Privatization;
    case PartnershipShare;
    case ExemptGoodsLeasing;
    case RegulatedCityTransport;
    case MunicipalWaste;
    case ExciseMarking;
    case HotelRoomTransfer;
    case PartnershipDistribution;
    case OccupiedTerritoryGoods;
    case DisasterRelief;
    case OilGasOperations;
    case FreeServitude;

    // Exempt with credit — Art. 172
    case ShipOrAircraftSupplies;
    case ExportTransport;
    case InternationalPassengerTransport;
    case IntermediaryInExemptOrForeign;
    case GasForPowerPlants;
    case TreatyProjectGoods;
    case InboundTransport;
    case DomesticAviation;
    case EmptyContainerServices;
    case WagonUseAcrossBorder;
    case SupplyToOccupiedTerritories;
    case DutyFree;
    case GoldToNationalBank;
    case InvestmentGold;
    case InboundTourism;
    case ShipServicesOnImport;
    case CustomsWarehouseGoods;
    case StateLottery;
    case PropertySeizedForBudget;
    case PrimaryAgriculturalProduce;
    case GeorgianMeatCheeseHazelnuts;
    case HotelLeaseback;
    case FreeHotelStay;
    case IncomingInternationalCalls;
    case InwardProcessing;
    case WholesaleElectricity;
    case ScrapMetal;
    case Books;
    case ElectricBus;
    case ImportRelatedServices;
    case GeorgianMedicalGoods;
    case FoodDonation;
    case UnderTreaty;

    // Exempt imports only — Art. 173
    case ListedMedicalImport;
    case Pharmaceuticals;
    case MedicalEquipment;
    case Paper;
    case PharmaRawMaterials;
    case AgriculturalInputs;
    case PassengerCarOrMotorcycle;
    case Boat;
    case ExciseStamps;
    case Treasure;
    case MoneyOrSecurities;
    case TractorParts;
    case Agrochemicals;
    case FuelInVehicleTank;
    case CivilAircraft;
    case ReturnedGoods;
    case HumanitarianAid;
    case DiplomaticGoods;
    case GrantGoods;

    // Place-of-supply categories — Art. 162¹
    case ImmovablePropertyService;
    case EventOrAdmission;
    case TransportAncillary;
    case MovablePropertyWork;
    case PassengerTransport;
    case GoodsTransport;
    case Restaurant;
    case ShortTermVehicleRental;
    case ElectronicService;
    case Telecom;
    case Broadcasting;
    case IntellectualProperty;
    case Advertising;
    case Consulting;
    case Staffing;
    case MovableRental;
    case Intermediary;
}
