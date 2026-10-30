<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Party;

/** Statuses the Tax Code attaches consequences to. */
enum Status
{
    case VatPayer;
    case Notary;
    case PrivateBailiff;
    case InternationalCompany;          // Art. 23
    case InvestmentCompany;             // Art. 23¹
    case FreeIndustrialZone;            // Art. 25
    case TouristEnterprise;             // Art. 26¹
    case SpecialEnterprise;             // Art. 24² — trade with occupied territories
    case HighMountainEnterprise;        // Art. 8.42 — dated: exempt for 10 years
    case LicensedFinancialInstitution;
    case CreditInstitution;             // bank, credit union, microfinance, lender — Art. 130.8
    case AgriculturalCooperative;       // Art. 130.4¹
    case AgriculturalProducer;          // Art. 82.1.ლ, 82.1.ც
    case GamblingOrganizer;             // Art. 130.9
    case DiplomaticMission;             // Art. 82.1.ა
    case AdministrativeBody;            // Art. 82.1.ჭ
    case BudgetOrganization;            // Art. 31
    case StateFoundedNonProfit;         // Art. 82.1.ბ¹
    case CharityOrganization;           // Art. 32
    case Broker;                        // Art. 154.1.ლ
    case PensionProvider;               // Art. 154.1.ჟ
    case PensionAgency;                 // Art. 154.1.რ
    case Bankrupt;                      // Art. 82.1.ჰ
    case WineOrAgroTourism;             // Art. 88.3 — 700,000 GEL threshold
    case PreferentialTaxJurisdiction;   // Art. 134.1¹
    case RegisteredTaxpayer;            // Art. 154.1.ლ
    case TaxableDealer;                 // VAT Art. 157.ქ, 161² — second-hand, art, antiques
    case ExciseProducer;                // VAT Art. 165.3 — must register before the first supply
}
