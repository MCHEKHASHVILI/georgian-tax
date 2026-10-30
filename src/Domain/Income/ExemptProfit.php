<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Income;

/**
 * Profit the Code lets a company distribute free of profit tax. Exempt profit is deemed paid out
 * first (98¹.5, 99.3), so a dividend up to that amount can be tagged with its source.
 */
enum ExemptProfit: string
{
    case ForeignDividends = '98¹.2.ე';          // dividends from a foreign company not in a preferential-tax country
    case PatriarchateReligiousItems = '99.1.დ';
    case AgriculturalCooperativeProduce = '99.1.ვ'; // until 1 January 2028
    case StateOrIfiBonds = '99.1.მ';            // also interest on NBG accounts
    case VirtualZoneItExports = '99.1.ჟ';
    case TouristZoneHotel = '99.1.რ';           // until 1 January 2026
    case GamblingClubOrTotalizator = '99.1.ს';
    case SpecialTradingCompany = '99.1.ტ';
    case Bankruptcy = '99.1.ფ';
    case ExpropriationCompensation = '99.1.ქ';
    case HighMountainEnterprise = '99.1.ღ';     // 10 years from the status
    case InterestFromLicensedInstitution = '99.1.ყ';
    case SpecialEnterprise = '99.1.შ';

    public function validUntil(): ?string
    {
        return match ($this) {
            self::AgriculturalCooperativeProduce => '2028-01-01',
            self::TouristZoneHotel => '2026-01-01',
            default => null,
        };
    }
}
