<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Goods;

use InvalidArgumentException;
use Mchekhashvili\Money\Money;

/**
 * Excise goods used as raw material for the excise goods a producer supplies — spirit for vodka,
 * say — and the excise paid or payable on them, which the producer may credit (189.1–2).
 *
 * $excise is the excise on the raw materials that went into the goods of this supply; spreading a
 * batch's raw materials over its supplies is for the caller. Without a document proving the excise
 * was paid there is no credit (189.3). When the raw materials made excise and non-excise goods
 * together, $exciseShare is the excise goods' share of what was made, by quantity (189.4).
 */
final readonly class ExciseInput
{
    public function __construct(
        public Money $excise,
        public ?ExciseEvidence $evidence,
        public float $exciseShare = 1.0,
        public string $description = 'raw materials',
    ) {
        if ($excise->isNegative()) {
            throw new InvalidArgumentException('The excise on raw materials cannot be negative.');
        }

        if ($exciseShare < 0 || $exciseShare > 1) {
            throw new InvalidArgumentException("The excise goods' share of the output must be between 0 and 1, got $exciseShare.");
        }
    }

    /** What 189.1–4 let the producer credit for this input, before the cap. */
    public function creditable(): Money
    {
        if ($this->evidence === null) {
            return Money::zero($this->excise->currency);
        }

        return $this->exciseShare === 1.0 ? $this->excise : $this->excise->multipliedBy($this->exciseShare);
    }
}
