<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Asset\Asset;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;

/**
 * Someone sells an asset. The income is the gain (Art. 82.4.ბ): price minus cost, never below zero.
 * Pass $broker when a brokerage pays the proceeds out — it becomes the tax agent (154.1.ლ).
 */
final readonly class Sell implements TaxableEvent
{
    public function __construct(
        public Party $seller,
        public Party $buyer,
        public Asset $asset,
        public Money $price,
        public DateTimeImmutable $on,
        public ?Party $broker = null,
        /** VAT 161² — a taxable dealer sells second-hand goods, art or antiques under the margin scheme. */
        public bool $marginScheme = false,
    ) {}

    public function gain(): Money
    {
        return $this->price->minus($this->asset->cost)->atLeastZero();
    }

    public function recipient(): Party { return $this->seller; }
    public function payer(): ?Party { return $this->broker ?? $this->buyer; }
    public function income(): Money { return $this->gain(); }
    public function date(): DateTimeImmutable { return $this->on; }

    public function kind(): IncomeKind
    {
        return $this->broker !== null ? IncomeKind::SecuritiesGainViaBroker : IncomeKind::AssetGain;
    }

    public function place(): Place
    {
        return new Place($this->asset->inGeorgia ? 'GE' : 'abroad', tiedToPropertyInGeorgia: $this->asset->inGeorgia);
    }
}
