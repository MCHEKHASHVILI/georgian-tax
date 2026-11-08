<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax;

use DateTimeImmutable;
use GeorgianTax\Domain\Event\OwnProperty;
use GeorgianTax\Domain\Event\TaxableEvent;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Property\PropertyItem;
use Mchekhashvili\TaxLegislation\Rate;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Law\Citation;
use Mchekhashvili\TaxLegislation\Rates\ThresholdTable;

/**
 * The subject of the property tax pipeline: one item held in one tax year.
 *
 * Due dates (205):
 *   company property   annual declaration and payment by 1 April of the next year (205.2)
 *   company land       by 15 November of the year (205.7)
 *   individual         declared by 1 November and paid by 15 November of the year after the
 *                      property year (205.12, 205.14); land, by 15 November of the land's year
 * The 15 June current payment companies make (205.3) is an instalment of this same amount.
 */
final readonly class Holding
{
    public const TAX = 'property';

    public function __construct(
        public TaxableEvent $event,
        public array $land,
        public MunicipalRates $rates,
        public ThresholdTable $thresholds,
        public PropertyCaps $caps,
        public PropertyCaps $landCaps,
        public ?OwnProperty $holding = null,
    ) {}

    public function of(OwnProperty $holding): self
    {
        return new self($this->event, $this->land, $this->rates, $this->thresholds, $this->caps, $this->landCaps, $holding);
    }

    /** 202.8 — the property tax rate is the one in force on 31 December of the tax year (the event's day). */
    public function propertyDay(): DateTimeImmutable
    {
        return $this->event->date();
    }

    /** 204.4 — the land tax is set by the rates in force on 1 April of the tax year. */
    public function landDay(): DateTimeImmutable
    {
        return self::landDayOf($this->event->date());
    }

    /** 1 April of the year $day falls in. */
    public static function landDayOf(DateTimeImmutable $day): DateTimeImmutable
    {
        return $day->setDate((int) $day->format('Y'), 4, 1);
    }

    /**
     * The council's figure for $article, checked against the Code's limits on the day the rate is
     * fixed (202.8, 204.4). Where the council set none: $default, or else the cap itself. Outside
     * the limits, or with neither a figure nor a cap, the assessment is unresolved.
     */
    public function councilRate(string $article, ?float $council, ?float $default = null): float|Unresolved
    {
        $land = str_starts_with($article, '204');
        $caps = $land ? $this->landCaps : $this->caps;
        $day = ($land ? $this->landDay() : $this->propertyDay())->format('Y-m-d');
        [$min, $max, $unit] = [$caps->min($article), $caps->max($article), $caps->unit($article)];
        $value = $council ?? $default ?? $max;

        if ($value === null) {
            return $this->unresolved(new Citation($article), "No council rate given, and no $article cap in force on $day to apply.");
        }

        if ($max !== null && $value > $max) {
            return $this->unresolved(new Citation($article), "The council's $value$unit exceeds the $article cap of $max$unit in force on $day.");
        }

        if ($min !== null && $value < $min) {
            return $this->unresolved(new Citation($article), "The council's $value$unit is below the $article minimum of $min$unit in force on $day.");
        }

        return $value;
    }

    /** A property tax threshold as it stood in the tax year. */
    public function threshold(string $key): Money
    {
        return $this->thresholds->threshold(self::TAX, $key, $this->event->date());
    }

    public function item(): PropertyItem
    {
        return $this->holding->item;
    }

    public function isIndividual(): bool
    {
        return $this->holding->owner->isIndividual();
    }

    /** 202.9, 205.8 — the share of the year the item was held, or the company existed. */
    public function share(): float
    {
        return max(0, min(12, $this->holding->months)) / 12;
    }

    /** A percentage of value (202). */
    public function taxed(object $basis, Money $base, float $percent, array $notes = []): Taxable
    {
        $rate = Rate::percent($percent);

        if ($this->share() < 1) {
            $notes[] = "Held {$this->holding->months} of 12 months.";
        }

        return new Taxable(
            self::TAX, self::cite($basis), $this->holding->owner, $base, $rate,
            $base->percent($rate->percent)->multipliedBy($this->share()), Collection::AnnualDeclaration, null, $this->due(), $notes,
            countsAsTurnover: false,
        );
    }

    /** So much per hectare or square metre (204). */
    public function levied(object $basis, float $area, string $unit, float $ratePerUnit, array $notes = []): Levied
    {
        return new Levied(
            self::TAX, self::cite($basis), $this->holding->owner, $area, $unit, Money::GEL($ratePerUnit),
            Money::GEL($area * $ratePerUnit), Collection::AnnualDeclaration, $this->due(), $notes,
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

    private function due(): DateTimeImmutable
    {
        $year = $this->holding->year;

        return match (true) {
            $this->item()->kind->isLand() => new DateTimeImmutable("$year-11-15"),
            $this->isIndividual() => new DateTimeImmutable(($year + 1) . '-11-15'),
            default => new DateTimeImmutable(($year + 1) . '-04-01'),
        };
    }

    private static function cite(object $basis): Citation
    {
        return $basis instanceof Citation ? $basis : Citation::of($basis);
    }
}
