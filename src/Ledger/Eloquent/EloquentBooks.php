<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger\Eloquent;

use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Ledger\CompanyBooks;
use GeorgianTax\Ledger\Eloquent\Models\CompanyYear;
use Mchekhashvili\Money\Money;

/**
 * Company figures from the company_years table. If your accounting already knows them, bind your
 * own CompanyBooks instead: $this->app->bind(CompanyBooks::class, MyBooks::class).
 */
final class EloquentBooks implements CompanyBooks
{
    public function revenue(Party $company, int $year): ?Money
    {
        return $this->year($company, $year)?->revenue;
    }

    public function netProfit(Party $company, int $year): ?Money
    {
        return $this->year($company, $year)?->net_profit;
    }

    public function foundedIn(Party $company): ?int
    {
        $year = CompanyYear::query()->where('company', $company->id)->max('founded_in');

        return $year === null ? null : (int) $year;
    }

    /** Records a closed year — call it from your year-end close. */
    public function close(Party $company, int $year, ?Money $revenue, ?Money $netProfit, ?int $foundedIn = null): CompanyYear
    {
        return CompanyYear::query()->updateOrCreate(
            ['company' => $company->id, 'year' => $year],
            ['revenue' => $revenue, 'net_profit' => $netProfit, 'founded_in' => $foundedIn],
        );
    }

    private function year(Party $company, int $year): ?CompanyYear
    {
        return CompanyYear::query()->where('company', $company->id)->where('year', $year)->first();
    }
}
