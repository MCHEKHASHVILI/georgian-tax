<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger;

use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Engine\TaxReport;

/**
 * Annual thresholds need history: the 1,000 GEL gift allowance, 150,000 GEL from distant heirs,
 * the small-business 500,000 GEL line, personal allowances. The engine reads totals *before*
 * the current event and records the event after assessing it.
 * Back it with your own database; InMemoryLedger is for tests.
 */
interface YearToDate
{
    /** Income the recipient received this year, optionally only of some kinds. */
    public function received(Party $recipient, int $year, IncomeKind ...$kinds): Money;

    /** Income the recipient received from one payer this year. */
    public function receivedFrom(Party $recipient, Party $payer, int $year, IncomeKind ...$kinds): Money;

    /** Gifts and inheritances received this year from heirs of the given orders. */
    public function receivedFromHeirs(Party $recipient, int $year, HeirOrder ...$orders): Money;

    /** What the payer paid out this year, optionally only of some kinds. */
    public function paid(Party $payer, int $year, IncomeKind ...$kinds): Money;

    /** Income-tax base already assessed this year — for the Art. 82.2 allowances. */
    public function taxableIncome(Party $taxpayer, int $year): Money;

    public function record(TaxableEvent $event, TaxReport $report): void;
}
