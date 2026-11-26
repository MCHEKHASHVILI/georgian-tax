<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Asset;

use DateTimeImmutable;
use GeorgianTax\Domain\Period;
use Mchekhashvili\Money\Money;

/**
 * Something an individual owns and may sell.
 * $acquiredAt is the date of the ownership document (Art. 82.4.ა.ა); for an I-order heir it may
 * be the deceased's or donor's date, since their holding period counts too (82.4.ა¹).
 * $cost is the purchase price, or the market price when received for free (82.4.ბ).
 */
final readonly class Asset
{
    public function __construct(
        public AssetType $type,
        public DateTimeImmutable $acquiredAt,
        public Money $cost,
        public bool $inGeorgia = true,
        public bool $usedInBusiness = false,
        public ?DateTimeImmutable $businessUseEndedAt = null,
    ) {}

    /** @param string|Period $period e.g. '2 years', or the Code's period */
    public function heldLongerThan(string|Period $period, DateTimeImmutable $on): bool
    {
        return $this->acquiredAt->modify("+$period") < $on;
    }

    /**
     * Art. 82.1.ვ.გ note — business use is ignored once 2 years have passed since it ended, or the
     * note's period when given (the engine passes the one in force on the day of the sale).
     */
    public function countsAsBusinessAssetOn(DateTimeImmutable $on, ?Period $ignoredAfter = null): bool
    {
        if (!$this->usedInBusiness) {
            return false;
        }

        return $this->businessUseEndedAt === null || $this->businessUseEndedAt->modify('+' . ($ignoredAfter ?? '2 years')) >= $on;
    }
}
