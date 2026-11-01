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
        public ?OwnProperty $holding = null,
    ) {}

    public function of(OwnProperty $holding): self
    {
        return new self($this->event, $this->land, $this->rates, $holding);
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
