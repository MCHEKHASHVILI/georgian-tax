<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use InvalidArgumentException;
use Mchekhashvili\Money\Money;

/**
 * A fixed asset a VAT payer uses, or means to use, both for operations with the right to credit and
 * for operations without it, where the two cannot be told apart (177.5): the VAT on it, whether it
 * is real estate, and the year it was put into use, from which 177.6 counts its 10 or 5 years.
 *
 * `$selfSupply` is set when the VAT is not a supplier's or the customs' but the VAT the person was
 * charged on supplying to itself: putting a building it built into use as a fixed asset (160.3.ბ),
 * or repairing its own building (160¹.2.ბ). 177.5–6 then apply as far as they would to the building
 * or the repair bought from another person (177.8) — which, for a building, is real estate.
 */
final readonly class FixedAsset
{
    public function __construct(
        /** The VAT on the asset that 177.5 apportions. */
        public Money $vat,
        /** Real estate (უძრავი ნივთი, a tenth a year over 10 years, 177.6.ა), or another fixed asset (a fifth over 5, 177.6.ბ). */
        public bool $realEstate,
        /** The calendar year it was put into use (ექსპლუატაციაში მიღება), the first of the 177.6 years. */
        public int $inUseFrom,
        /** The self-supply the VAT was charged on, under 177.8. */
        public ?SelfSupplyReason $selfSupply = null,
    ) {
        if ($vat->isNegative()) {
            throw new InvalidArgumentException("A fixed asset's VAT cannot be negative.");
        }

        if ($selfSupply !== null && !in_array($selfSupply, [SelfSupplyReason::OwnBuildingPutIntoUse, SelfSupplyReason::OwnBuildingRepair], true)) {
            throw new InvalidArgumentException("177.8 reaches only 160.3.ბ and 160¹.2.ბ, not {$selfSupply->value}.");
        }

        if ($selfSupply === SelfSupplyReason::OwnBuildingPutIntoUse && !$realEstate) {
            throw new InvalidArgumentException('A building put into use under 160.3.ბ is real estate (177.8).');
        }
    }

    /** Over how many calendar years 177.6 spreads the VAT: 10 for real estate, 5 for the rest. */
    public function years(): int
    {
        return $this->realEstate ? 10 : 5;
    }

    /** The 177.6 subparagraph for the asset's kind. */
    public function spreadRef(): string
    {
        return $this->realEstate ? '177.6.ა' : '177.6.ბ';
    }

    /** The last calendar year of the 177.6 spread. */
    public function lastYear(): int
    {
        return $this->inUseFrom + $this->years() - 1;
    }

    /** One year's part of the VAT: a tenth or a fifth (177.6). */
    public function yearsPart(): Money
    {
        return $this->vat->dividedBy($this->years());
    }

    public function __toString(): string
    {
        return ($this->realEstate ? 'real estate' : 'a fixed asset') . " in use from {$this->inUseFrom}, VAT {$this->vat}";
    }
}
