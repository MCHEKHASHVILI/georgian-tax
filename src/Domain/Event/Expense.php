<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;

/**
 * A company spends money in a way profit tax looks at: non-business costs (98²), representation
 * costs (98⁴), or payments to preferential-tax-country or tax-exempt persons (98².3). Ordinary
 * business costs need no event. Creates no income, so income tax and VAT ignore it — pay the
 * counterparty with a Pay if they should see it.
 */
final readonly class Expense implements TaxableEvent
{
    public function __construct(
        public Party $company,
        public Money $amount,
        public DateTimeImmutable $on,
        public ExpenseNature $nature,
    ) {}

    public function recipient(): Party { return $this->company; }
    public function payer(): ?Party { return $this->company; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::zero(); }
    public function date(): DateTimeImmutable { return $this->on; }
    public function place(): Place { return Place::georgia(); }
}
