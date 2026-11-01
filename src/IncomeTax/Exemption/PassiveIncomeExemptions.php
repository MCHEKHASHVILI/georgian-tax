<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Exemption;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use GeorgianTax\IncomeTax\Assessment;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Dividends and interest released by who pays them:
 *   23.8    dividend from an international company;
 *   23¹.9   dividend from an investment company to an individual or non-resident;
 *   130.4¹  dividend from an agricultural cooperative, until 2028;
 *   130.7   dividend from a free-industrial-zone company;
 *   130.8   dividend of a bank or lender from 2023+ profits;
 *   130.9   dividend of a gaming-hall or online gambling organizer from 2025+ profits of that activity;
 *   131.5   interest from a licensed financial institution, unless the recipient is one too;
 *   131.9   interest from a free-industrial-zone company.
 */
final class PassiveIncomeExemptions implements Exemption
{
    public function exempts(Assessment $a): ?Exempt
    {
        $profitYear = $a->payment()?->profitYear;

        if ($a->is(IncomeKind::Dividend)) {
            return match (true) {
                $a->payerIs(Status::InternationalCompany) => $a->exempt(new Citation('23.8'), 'Dividends from an international company are exempt.'),
                $a->payerIs(Status::InvestmentCompany) => $a->exempt(new Citation('23¹.9'), 'Dividends from an investment company are exempt.'),
                $a->payerIs(Status::AgriculturalCooperative) && $a->before('2028-01-01')
                    => $a->exempt(new Citation('130.4¹'), 'Dividends from an agricultural cooperative are exempt until 2028.'),
                $a->payerIs(Status::FreeIndustrialZone) => $a->exempt(new Citation('130.7'), 'Dividends from a free-industrial-zone company are exempt.'),
                $a->payerIs(Status::CreditInstitution) && $profitYear !== null && $profitYear >= 2023
                    => $a->exempt(new Citation('130.8'), "A bank's or lender's dividends from 2023+ profits are exempt."),
                $a->payerIs(Status::GamblingOrganizer) && $profitYear !== null && $profitYear >= 2025
                    => $a->exempt(new Citation('130.9'), "A gambling organizer's dividends from 2025+ profits are exempt."),
                default => null,
            };
        }

        if ($a->is(IncomeKind::Interest)) {
            return match (true) {
                $a->payerIs(Status::LicensedFinancialInstitution) && !$a->taxpayer()->is(Status::LicensedFinancialInstitution)
                    => $a->exempt(new Citation('131.5'), 'Interest from a licensed financial institution is exempt.'),
                $a->payerIs(Status::FreeIndustrialZone) => $a->exempt(new Citation('131.9'), 'Interest from a free-industrial-zone company is exempt.'),
                default => null,
            };
        }

        return null;
    }
}
