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
 * The first reporting period of a fixed asset in mixed use (177.5): the month whose declaration
 * would credit its VAT if it served taxable operations only. VAT answers with what may be credited
 * that month — all of it when last year's operations without the right to credit were under 20% of
 * the total turnover (177.5.ა), and otherwise nothing yet: the VAT is then credited only at each
 * year's end (177.5.ბ, `CloseFixedAssetYear`).
 *
 * `$priorYear` is last year's turnover: the total turnover is the year's supplies of goods and
 * services without VAT (177.5.ა), the caller's figures. With none, or none given, 177.5.ა does not
 * apply.
 */
final readonly class CreditFixedAsset implements TaxableEvent
{
    public function __construct(
        public Party $taxpayer,
        public int $year,
        public int $month,
        public FixedAsset $asset,
        /** Last year's turnover (177.5.ა). */
        public ?Turnover $priorYear = null,
    ) {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("There is no month $month.");
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
