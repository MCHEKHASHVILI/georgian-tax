<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Exemption;

use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Citation;
use GeorgianTax\ProfitTax\Outflow;

/**
 * Companies whose distributions and outflows are released by their status:
 *   99.1.ნ  a free-industrial-zone company, for its permitted activity;
 *   99.1.ფ  a company in bankruptcy;
 *   99.1.ღ  a high-mountain enterprise, for 10 calendar years from the status;
 *   23¹.8   an investment fund, for its permitted activity.
 */
final class ExemptCompanies implements Exemption
{
    public function exempts(Outflow $o): ?Outcome
    {
        $company = $o->company;
        $mountainSince = $company->since(Status::HighMountainEnterprise);

        return match (true) {
            $company->is(Status::FreeIndustrialZone) => $o->exempt(new Citation('99.1.ნ'), 'A free-industrial-zone company is exempt for its permitted activity.'),
            $company->is(Status::Bankrupt) => $o->exempt(new Citation('99.1.ფ'), 'A company in bankruptcy is exempt.'),
            $mountainSince !== null && $o->year() <= (int) $mountainSince->format('Y') + 9
                => $o->exempt(new Citation('99.1.ღ'), 'A high-mountain enterprise is exempt for 10 years.'),
            $company->is(Status::InvestmentCompany) => $o->exempt(new Citation('23¹.8'), 'An investment fund is exempt for its permitted activity.'),
            default => null,
        };
    }
}
