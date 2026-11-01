<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Party;

final readonly class Inherit implements TaxableEvent
{
    public function __construct(
        public Party $heir,
        public Money $value,
        public DateTimeImmutable $on,
        public HeirOrder $relation,
        public bool $propertyInGeorgia = true,
    ) {}

    public function recipient(): Party { return $this->heir; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Inheritance; }
    public function income(): Money { return $this->value; }
    public function date(): DateTimeImmutable { return $this->on; }

    public function place(): Place
    {
        return new Place($this->propertyInGeorgia ? 'GE' : 'abroad', tiedToPropertyInGeorgia: $this->propertyInGeorgia);
    }
}
