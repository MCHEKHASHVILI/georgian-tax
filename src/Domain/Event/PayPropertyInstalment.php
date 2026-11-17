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
 * A company's or organization's current payment (მიმდინარე გადასახდელი) of property tax for a
 * tax year: the past year's annual tax on its property, paid by 15 June of the year (205.3). It is
 * paid on account of the year's own tax, which is declared and settled by 1 April of the next
 * (205.2) — not a tax on top of it. Land is not part of it: a company pays its land tax by
 * 15 November (205.7).
 *
 * `$lastYearTax` is the past year's annual property tax on property, as declared (205.2) — the
 * caller's figure. For a company founded during the past year it is that year's tax in full (205.9).
 *
 * The Code's three exceptions:
 *   205.4  the year's expected liability, reliefs included, is at least 50% below last year's, and the
 *          tax authority was told by 1 June: the payment may be reduced, or not paid at all —
 *          `$expectedTax`, `$notifiedOn`, and `$reducedTo` the amount chosen (none: nothing paid)
 *   205.6  the rate changed from the previous period: the payment may be made at the year's rate —
 *          `$atCurrentRates`, last year's property at this year's rates, the caller's figure
 *   205.9  a legal person founded after the start of the year pays no current payment for it —
 *          `$foundedOn`
 *
 * It creates no income, so the other taxes pass it by.
 */
final readonly class PayPropertyInstalment implements TaxableEvent
{
    /** 205.3 — the current payment is due by 15 June of the tax year. */
    public const DUE = '06-15';

    /** 205.4 — the tax authority must be told of the expected reduction by 1 June. */
    public const NOTICE_BY = '06-01';

    public function __construct(
        public Party $company,
        public int $year,
        /** The past year's annual property tax on property (205.2–3). */
        public Money $lastYearTax,
        /** 205.4 — the year's expected property tax, reliefs included. */
        public ?Money $expectedTax = null,
        /** 205.4 — when the tax authority was told of the expected reduction. */
        public ?DateTimeImmutable $notifiedOn = null,
        /** 205.4 — what the company chose to pay instead, if anything. */
        public ?Money $reducedTo = null,
        /** 205.6 — last year's property at this year's rates. */
        public ?Money $atCurrentRates = null,
        /** 205.9 — the day the company was founded, when it matters. */
        public ?DateTimeImmutable $foundedOn = null,
    ) {
        $amounts = ['lastYearTax' => $lastYearTax, 'expectedTax' => $expectedTax, 'reducedTo' => $reducedTo, 'atCurrentRates' => $atCurrentRates];

        foreach ($amounts as $name => $amount) {
            if ($amount?->isNegative()) {
                throw new InvalidArgumentException("A current payment's $name cannot be negative.");
            }
        }

        if ($reducedTo !== null && $reducedTo->greaterThan($lastYearTax)) {
            throw new InvalidArgumentException("A current payment reduced under 205.4 cannot exceed last year's tax of $lastYearTax.");
        }
    }

    /** The last day to tell the tax authority of an expected reduction (205.4). */
    public function noticeBy(): DateTimeImmutable
    {
        return new DateTimeImmutable("{$this->year}-" . self::NOTICE_BY);
    }

    /** Whether the company was founded after the year began (205.9). */
    public function foundedInYear(): bool
    {
        return $this->foundedOn !== null
            && (int) $this->foundedOn->format('Y') === $this->year
            && $this->foundedOn->format('m-d') !== '01-01';
    }

    public function recipient(): Party { return $this->company; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    /** 205.3 — 15 June of the tax year. */
    public function date(): DateTimeImmutable { return new DateTimeImmutable("{$this->year}-" . self::DUE); }
    public function place(): Place { return Place::georgia(); }
}
