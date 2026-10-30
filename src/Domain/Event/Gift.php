<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;

final readonly class Gift implements TaxableEvent
{
    public function __construct(
        public Party $donor,
        public Party $recipient,
        public Money $value,
        public DateTimeImmutable $on,
        public HeirOrder $relation = HeirOrder::None,
        /** Art. 82.1.თ — gifts from an employer never use the 1,000 GEL allowance. */
        public bool $fromEmployer = false,
        public bool $propertyInGeorgia = true,
    ) {}

    public function recipient(): Party { return $this->recipient; }
    public function payer(): ?Party { return $this->donor; }
    public function kind(): IncomeKind { return IncomeKind::Gift; }
    public function income(): Money { return $this->value; }
    public function date(): DateTimeImmutable { return $this->on; }

    public function place(): Place
    {
        return new Place($this->propertyInGeorgia ? 'GE' : 'abroad', tiedToPropertyInGeorgia: $this->propertyInGeorgia);
    }
}
