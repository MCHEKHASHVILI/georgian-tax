<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax;

use GeorgianTax\Engine\Deadline;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Goods\Commodity;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Rate;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * The subject of the import tax pipeline: goods crossing the customs border into Georgia (Art. 195–196).
 * Import tax is paid as an import payment, at customs (198.2).
 */
final readonly class Clearance
{
    public const TAX = 'import';

    public function __construct(
        public TaxableEvent $event,
        public ImportTable $table,
        public ?Import $import = null,
        public ?Commodity $commodity = null,
    ) {}

    public function of(Import $import, Commodity $commodity): self
    {
        return new self($this->event, $this->table, $import, $commodity);
    }

    public function year(): int
    {
        return (int) $this->event->date()->format('Y');
    }

    /** A percentage of customs value (197.1–2, 198.1). Import tax is not turnover. */
    public function taxed(object $basis, int $percent): Taxable
    {
        $rate = Rate::percent($percent);
        $base = $this->import->customsValue;

        return new Taxable(
            self::TAX, self::cite($basis), $this->import->importer, $base, $rate, $base->percent($rate->percent),
            Collection::AtCustoms, null, Deadline::of(Collection::AtCustoms, $this->event->date()),
            countsAsTurnover: false,
        );
    }

    /**
     * A specific duty (197.3, 197.6). The amount is computed exactly; the per-unit rate on the
     * outcome is rounded to tetri for display.
     *
     * @param list<string> $notes
     */
    public function levied(object $basis, float $quantity, string $unit, float $ratePerUnit, ?Money $extra = null, array $notes = []): Levied
    {
        return new Levied(
            self::TAX, self::cite($basis), $this->import->importer, $quantity, $unit, Money::GEL($ratePerUnit),
            Money::GEL($quantity * $ratePerUnit)->plus($extra ?? Money::GEL(0)),
            Collection::AtCustoms, Deadline::of(Collection::AtCustoms, $this->event->date()), $notes,
        );
    }

    public function exempt(object $basis, string $reason): Exempt
    {
        return new Exempt(self::TAX, self::cite($basis), $reason);
    }

    public function notTaxable(object $basis, string $reason): NotTaxable
    {
        return new NotTaxable(self::TAX, self::cite($basis), $reason);
    }

    public function unresolved(object $basis, string $reason): Unresolved
    {
        return new Unresolved(self::TAX, self::cite($basis), $reason);
    }

    private static function cite(object $basis): Citation
    {
        return $basis instanceof Citation ? $basis : Citation::of($basis);
    }
}
