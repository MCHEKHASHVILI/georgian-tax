<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Period;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Party\Party;
use InvalidArgumentException;
use Mchekhashvili\Money\Money;

/**
 * A passenger car (HS 8703) or motorcycle (HS 8711, mopeds included) released into free
 * circulation and then placed under the export procedure, leaving Georgia. If that happens within
 * 180 calendar days of the release, the importer may have back the excise paid on the vehicle
 * (189.8, cars and motorcycles) and the import tax (198.3, cars only), 100% of it.
 *
 * `$released` is the import as it was released — its day, importer, commodity, customs value and
 * relief — so excise and import tax work out what was paid then and answer with its refund, a
 * negative `Levied` — or, when the importer knows what it paid, `$excisePaid` and `$importTaxPaid`
 * are refunded instead. `$exportedOn` is the day the export declaration placed the vehicle under
 * the export procedure; `$leftGeorgia` whether it has left Georgia's territory, which both articles
 * also require. The refund follows "the established procedure", which the Code does not hold; its
 * outcome is dated the export day. VAT, income, profit and property tax find nothing to charge.
 */
final readonly class ReexportVehicle implements TaxableEvent
{
    /**
     * 189.8, 198.3 — calendar days from the release in which the vehicle must be exported, as
     * consolidated. The refunds read the period in force on the export day from the law
     * (`excise.periods`, `import.periods`), so an amendment changes it without code.
     */
    public const DAYS = 180;

    public function __construct(
        public Import $released,
        public DateTimeImmutable $exportedOn,
        public bool $leftGeorgia = true,
        /** The excise actually paid on the release, if known; otherwise worked out from `$released`. */
        public ?Money $excisePaid = null,
        /** The import tax actually paid on the release, if known; otherwise worked out from `$released`. */
        public ?Money $importTaxPaid = null,
    ) {
        if ($exportedOn->setTime(0, 0) < $released->on->setTime(0, 0)) {
            throw new InvalidArgumentException('A vehicle cannot be exported before it was released into free circulation.');
        }

        if ($excisePaid?->isNegative() || $importTaxPaid?->isNegative()) {
            throw new InvalidArgumentException('The tax paid on a vehicle cannot be negative.');
        }
    }

    /**
     * The last day the vehicle may be placed under the export procedure: the period (180 calendar
     * days unless given) counted from the day after the release (3.2). Not modelled: moving a last
     * day that is not a working day to the next working day (3.6).
     */
    public function lastDay(?Period $period = null): DateTimeImmutable
    {
        return ($period ?? Period::days(self::DAYS))->after($this->released->on);
    }

    /** Whether it was exported in time and has left Georgia — both 189.8 and 198.3 require it. */
    public function qualifies(?Period $period = null): bool
    {
        return $this->leftGeorgia && $this->exportedOn->setTime(0, 0) <= $this->lastDay($period);
    }

    public function recipient(): Party { return $this->released->importer; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    public function date(): DateTimeImmutable { return $this->exportedOn; }
    public function place(): Place { return Place::georgia(); }
}
