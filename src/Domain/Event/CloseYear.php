<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Party\Party;
use InvalidArgumentException;
use Mchekhashvili\Money\Money;

/**
 * A bank, credit union, microfinance organization or lender (`Status::CreditInstitution`) closing
 * its calendar year — the event its profit tax looks at. Unlike other companies, it is taxed on the
 * year's gross income less the Code's deductions (97.12), at 20% (98.4), from 2023 (309.94).
 *
 * The figures are the caller's: the Code's gross income (ერთობლივი შემოსავალი) and deductions
 * (Chapter XV, the reserves of 109 among them) are not computed here.
 * `$lossesCarried` is the excess of deductions over gross income from earlier years still within its
 * 5-year term (121.4) or the 10-year term chosen under 122; which years are still open is for the
 * caller. `$currentPayments` are the 155.1 instalments paid during the year, credited against its tax
 * (155.6); they change what is left to pay, not the tax.
 *
 * It creates no income, so the other taxes pass it by.
 */
final readonly class CloseYear implements TaxableEvent
{
    public function __construct(
        public Party $company,
        public int $year,
        /** The year's gross income (97.12). */
        public Money $grossIncome,
        /** The deductions the Code allows against it (97.12, Chapter XV). */
        public Money $deductions,
        /** Earlier years' losses still carried forward (121.4, 122). */
        public ?Money $lossesCarried = null,
        /** Current payments made for the year (155.1, 155.6). */
        public ?Money $currentPayments = null,
    ) {
        foreach (['grossIncome' => $grossIncome, 'deductions' => $deductions, 'lossesCarried' => $lossesCarried, 'currentPayments' => $currentPayments] as $name => $amount) {
            if ($amount?->isNegative()) {
                throw new InvalidArgumentException("A year's $name cannot be negative.");
            }
        }
    }

    public function losses(): Money
    {
        return $this->lossesCarried ?? Money::zero($this->grossIncome->currency);
    }

    public function paid(): Money
    {
        return $this->currentPayments ?? Money::zero($this->grossIncome->currency);
    }

    public function recipient(): Party { return $this->company; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    public function date(): DateTimeImmutable { return new DateTimeImmutable("{$this->year}-12-31"); }
    public function place(): Place { return Place::georgia(); }
}
