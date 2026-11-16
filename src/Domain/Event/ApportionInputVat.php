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
 * A VAT payer's input VAT for one month on goods and services used both for operations with the
 * right to credit and for operations without it (177.1) — the part that cannot be attributed to
 * either. VAT answers with the part it may credit that month, a negative `Levied`.
 *
 * The month's share is last year's annual percentage (177.4.ა) when one was fixed — give
 * `$priorYear` — and otherwise the month's own (177.4.ბ) — give `$month`. Either way it is a
 * provisional figure: the year's last reporting period corrects it to the year's percentage
 * (`CorrectApportionment`).
 *
 * `$inputVat` is VAT the person could credit under 174–176 if the goods served taxable operations
 * only: VAT 178 refuses outright (representation, a defective invoice, …) is not part of it, and
 * input VAT attributable wholly to one kind of operation is credited (or not) without apportionment.
 * Fixed assets used for both kinds of operation go by 177.5–6 instead, which this event does not
 * model.
 */
final readonly class ApportionInputVat implements TaxableEvent
{
    public function __construct(
        public Party $taxpayer,
        public int $year,
        public int $month,
        /** The month's input VAT on goods and services used for both kinds of operation (177.1). */
        public Money $inputVat,
        /** The month's turnover (177.4.ბ), needed when no annual percentage was fixed last year. */
        public ?Turnover $monthTurnover = null,
        /** Last year's annual turnover, whose percentage applies when it was fixed (177.4.ა). */
        public ?Turnover $priorYear = null,
    ) {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("There is no month $month.");
        }

        if ($inputVat->isNegative()) {
            throw new InvalidArgumentException('Input VAT to apportion cannot be negative.');
        }

        if ($monthTurnover === null && $priorYear === null) {
            throw new InvalidArgumentException("Give the month's turnover or last year's (177.4).");
        }
    }

    public function recipient(): Party { return $this->taxpayer; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    public function place(): Place { return Place::georgia(); }

    /** The month's last day: the end of the VAT reporting period. */
    public function date(): DateTimeImmutable
    {
        return (new DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $this->month)))->modify('last day of this month');
    }
}
