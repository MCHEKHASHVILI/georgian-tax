<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger;

use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Party;

final class StaticBooks implements CompanyBooks
{
    /** @var array<string, array<int, array{revenue?: Money, netProfit?: Money}>> */
    private array $years = [];

    /** @var array<string, int> */
    private array $founded = [];

    public function set(Party $company, int $year, ?Money $revenue = null, ?Money $netProfit = null): self
    {
        $this->years[$company->id][$year] = array_filter(['revenue' => $revenue, 'netProfit' => $netProfit]);

        return $this;
    }

    public function founded(Party $company, int $year): self
    {
        $this->founded[$company->id] = $year;

        return $this;
    }

    public function revenue(Party $company, int $year): ?Money
    {
        return $this->years[$company->id][$year]['revenue'] ?? null;
    }

    public function netProfit(Party $company, int $year): ?Money
    {
        return $this->years[$company->id][$year]['netProfit'] ?? null;
    }

    public function foundedIn(Party $company): ?int
    {
        return $this->founded[$company->id] ?? null;
    }
}
