<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Income\ExemptProfit;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Income\SupplyCategory;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;

/**
 * One party pays another for something: salary, a service, rent, interest, a dividend, a pension...
 * The optional facts are the ones specific articles turn on; leave them at their defaults otherwise.
 */
final readonly class Pay implements TaxableEvent
{
    public Place $place;

    public function __construct(
        public Party $payer,
        public Party $payee,
        public IncomeKind $for,
        public Money $amount,
        public DateTimeImmutable $on,
        ?Place $place = null,
        /** Art. 133³ — the goods are on the Minister of Finance's list. */
        public bool $listedGoods = false,
        /** Art. 133³.2 — a waybill was issued for the supply. */
        public bool $waybillIssued = false,
        /** Art. 130.8, 130.9 — the year whose profit funds a dividend. */
        public ?int $profitYear = null,
        /** Art. 82.1.შ — days the non-resident worked in Georgia this year. */
        public ?int $daysWorkedInGeorgia = null,
        /** Art. 82.1.შ, 154.1.ა.ბ — the salary is a cost of the employer's Georgian PE. */
        public bool $chargedToGeorgianPE = false,
        /** Art. 81.2 — the landlord takes no deductions on residential rent (registered for 5%). */
        public bool $noDeductionsOnResidentialRent = false,
        /** Art. 95⁵ — the income comes from the payee's fixed-tax activity. */
        public bool $fromFixedTaxActivity = false,
        /** Art. 82.1.ტ–უ², 131.8 — the security interest is paid on. */
        public ?AssetType $instrument = null,
        /** Art. 131.8 — the bond is listed on a recognized foreign exchange. */
        public bool $listedAbroad = false,
        /** VAT — what is supplied, where an exemption or place-of-supply rule turns on it. */
        public ?SupplyCategory $supply = null,
        /** VAT — $amount already includes VAT (164.1 taxes the price without it). */
        public bool $pricesIncludeVat = false,
        /** Excise — the excise goods supplied, for a producer's supply (184). */
        public ?Commodity $commodity = null,
        /** Profit tax 99.3 — the dividend is paid out of profit the Code exempts. */
        public ?ExemptProfit $fromExemptProfit = null,
    ) {
        $this->place = $place ?? Place::georgia();
    }

    public function recipient(): Party { return $this->payee; }
    public function payer(): ?Party { return $this->payer; }
    public function kind(): IncomeKind { return $this->for; }
    public function income(): Money { return $this->amount; }
    public function date(): DateTimeImmutable { return $this->on; }
    public function place(): Place { return $this->place; }
}
