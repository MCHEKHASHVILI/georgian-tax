<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger;

use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Party;

/**
 * Figures from a company's own accounts that profit-tax limits are measured against.
 * Back it with your accounting system; StaticBooks is for tests and simple setups.
 */
interface CompanyBooks
{
    /** Art. 98⁴.2 — representation costs above 1% of this are taxed. Null if not known. */
    public function revenue(Party $company, int $year): ?Money;

    /** Art. 98³.3.ა — charity donations above 10% of this are taxed. Null if not known. */
    public function netProfit(Party $company, int $year): ?Money;

    /** Art. 98⁴.3 — a company in its founding year measures against its own costs. */
    public function foundedIn(Party $company): ?int;
}
