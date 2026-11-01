<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Goods\ImportRelief;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Income\SupplyCategory;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Party;

/**
 * Goods released into free circulation (VAT Art. 160²). Creates no income, so income tax ignores it.
 * The VAT base is the customs value plus import taxes and fees other than VAT, and transport and
 * insurance to the first destination in Georgia (164¹.1–2).
 */
final readonly class Import implements TaxableEvent
{
    public function __construct(
        public Party $importer,
        public Money $customsValue,
        public DateTimeImmutable $on,
        public ?Money $dutiesAndFees = null,
        public ?Money $transportToDestination = null,
        public ?SupplyCategory $goods = null,
        /** VAT 168.3 — Government-listed HS 8401–9033 goods: VAT due within 45 days. */
        public bool $onDeferredPaymentList = false,
        /** Excise and import tax — HS code and measurements. */
        public ?Commodity $commodity = null,
        /** Import tax (199) and excise (194.5) relief, if any. */
        public ?ImportRelief $relief = null,
        /** Import tax 199.რ — country of origin. */
        public ?string $originCountry = null,
        /** Import tax 197.3 — GEL per euro on the day, for euro-denominated rates on alcohol. */
        public ?float $eurToGel = null,
    ) {}

    /** Whether the importer named its own duties; if not, VAT takes them from excise and import tax. */
    public function dutiesGiven(): bool
    {
        return $this->dutiesAndFees !== null;
    }

    public function taxableAmount(): Money
    {
        return $this->customsValue
            ->plus($this->dutiesAndFees ?? Money::GEL(0))
            ->plus($this->transportToDestination ?? Money::GEL(0));
    }

    public function recipient(): Party { return $this->importer; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    public function date(): DateTimeImmutable { return $this->on; }
    public function place(): Place { return Place::georgia(); }
}
