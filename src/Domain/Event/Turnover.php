<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use InvalidArgumentException;
use Mchekhashvili\Money\Money;

/**
 * Turnover split for the 177.2 fraction, without VAT: the operations whose input VAT may be credited
 * under 175 (the numerator, 177.2.ა) and those whose input VAT may not (added to it for the
 * denominator, 177.2.ბ).
 *
 * The figures are the caller's, as 177.3 and 177.9 shape them: left out are the supply of a fixed
 * asset used in the person's own activity (177.3.ა), and real-estate and financial operations that
 * are not the person's principal activity (177.3.ბ); included is the amount taxed under 163.2 (177.9).
 */
final readonly class Turnover
{
    public function __construct(
        /** Turnover of operations with the right to credit (177.2.ა). */
        public Money $creditable,
        /** Turnover of operations without it (177.2.ბ). */
        public Money $nonCreditable,
    ) {
        if ($creditable->isNegative() || $nonCreditable->isNegative()) {
            throw new InvalidArgumentException('Turnover cannot be negative.');
        }
    }

    /** The denominator of 177.2.ბ. */
    public function total(): Money
    {
        return $this->creditable->plus($this->nonCreditable);
    }

    /** The creditable share, 0–1 (177.2); null when there is no turnover to divide by. */
    public function share(): ?float
    {
        $total = $this->total()->minor;

        return $total === 0 ? null : $this->creditable->minor / $total;
    }

    /** The share as the Code fixes it, a percentage (177.2), for the notes. */
    public static function percent(float $share): string
    {
        return rtrim(rtrim(number_format($share * 100, 2, '.', ''), '0'), '.') . '%';
    }

    public function __toString(): string
    {
        return "{$this->creditable} with credit / {$this->total()} in all";
    }
}
