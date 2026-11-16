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
 * The year-end correction of 177.4: in the year's last reporting period, the input VAT apportioned
 * during the year (`ApportionInputVat`) is credited at the year's own annual percentage (177.2), or
 * in full when the operations without the right to credit came to less than 5% of the turnover
 * (177.7). VAT answers with the difference from what was credited, as a `Levied`: negative when
 * more may be credited, positive when credit is given back.
 *
 * `$inputVat` is the year's input VAT on goods and services used for both kinds of operation,
 * December's included; `$credited` is what the year's declarations credited of it. December may be
 * apportioned first and its credit counted in `$credited`, or left to this correction alone — the
 * result is the same.
 */
final readonly class CorrectApportionment implements TaxableEvent
{
    public function __construct(
        public Party $taxpayer,
        public int $year,
        /** The year's input VAT on goods and services used for both kinds of operation (177.1). */
        public Money $inputVat,
        /** What the year's declarations credited of it (177.4). */
        public Money $credited,
        /** The year's turnover (177.2, 177.7). */
        public Turnover $turnover,
    ) {
        if ($inputVat->isNegative() || $credited->isNegative()) {
            throw new InvalidArgumentException('Input VAT and the credit taken cannot be negative.');
        }
    }

    public function recipient(): Party { return $this->taxpayer; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    public function date(): DateTimeImmutable { return new DateTimeImmutable("{$this->year}-12-31"); }
    public function place(): Place { return Place::georgia(); }
}
