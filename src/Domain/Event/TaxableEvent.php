<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Party;

/**
 * A verb: something happened that may create a tax. Pay, Sell, Gift, Inherit and Refund implement it.
 * Each tax in the engine reads the same event and decides for itself.
 */
interface TaxableEvent
{
    /** Who receives the income — the potential income taxpayer. */
    public function recipient(): Party;

    /** Who pays it — the potential tax agent. Null when nobody pays (e.g. an inheritance). */
    public function payer(): ?Party;

    public function kind(): IncomeKind;

    /** The income this event creates: gross for a payment, gain for a sale, value for a gift. */
    public function income(): Money;

    public function date(): DateTimeImmutable;

    public function place(): Place;
}
