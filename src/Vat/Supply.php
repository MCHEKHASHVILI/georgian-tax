<?php

declare(strict_types=1);

namespace GeorgianTax\Vat;

use GeorgianTax\Engine\Deadline;
use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\SelfSupply;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\SupplyCategory;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\Outcome\ExemptWithCredit;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Law\Citation;
use GeorgianTax\Ledger\YearToDate;
use Mchekhashvili\TaxLegislation\Rates\RateTable;
use Mchekhashvili\TaxLegislation\Rates\ThresholdTable;

/**
 * The subject flowing through the VAT stages: the event read as a supply — who supplies, who
 * receives, what, and for how much — plus the outcome builders the rules finish with.
 *
 * Seen from VAT, the parties of an event swap roles compared with income tax: the one *paid*
 * is the supplier, the one *paying* is the customer.
 */
final readonly class Supply
{
    public const TAX = 'vat';
    public const VAT_FRACTION = 1.18;

    public function __construct(
        public TaxableEvent $event,
        public YearToDate $ledger,
        public RateTable $rates,
        public ThresholdTable $thresholds,
        /** Excise and import tax the earlier stages levied — part of the import base (164¹.2.ა). */
        public ?Money $earlierDuties = null,
    ) {}

    /** A VAT threshold as it stood on the event's day. */
    public function threshold(string $key): Money
    {
        return $this->thresholds->threshold(self::TAX, $key, $this->event->date());
    }

    /* ── Who and what ──────────────────────────────────────── */

    public function supplier(): Party
    {
        return match (true) {
            $this->event instanceof Gift => $this->event->donor,
            default => $this->event->recipient(), // the payee, seller, importer or self-supplier
        };
    }

    public function customer(): ?Party
    {
        return match (true) {
            $this->event instanceof Pay => $this->event->payer,
            $this->event instanceof Sell => $this->event->buyer,
            $this->event instanceof Gift => $this->event->recipient,
            default => null,
        };
    }

    public function isImport(): bool
    {
        return $this->event instanceof Import;
    }

    public function isGoods(): bool
    {
        return match (true) {
            $this->event instanceof Pay => $this->event->for === IncomeKind::GoodsSupply,
            $this->event instanceof SelfSupply => !in_array($this->event->reason->name, ['EmployeeServices', 'OwnBuildingRepair'], true),
            default => true, // sales, gifts and imports move goods
        };
    }

    public function isService(): bool
    {
        return !$this->isImport() && !$this->isGoods();
    }

    /** 162¹.2 — for place of supply, anyone but a non-entrepreneur individual counts as a business. */
    public function customerIsBusiness(): bool
    {
        $customer = $this->customer();

        return $customer !== null && $customer->kind !== PartyKind::Individual;
    }

    /** The category given on the event, or the one its kind and asset imply. */
    public function category(): ?SupplyCategory
    {
        $explicit = match (true) {
            $this->event instanceof Pay => $this->event->supply,
            $this->event instanceof Gift => $this->event->what,
            $this->event instanceof Import => $this->event->goods,
            default => null,
        };

        if ($explicit !== null) {
            return $explicit;
        }

        if ($this->event instanceof Sell) {
            return match ($this->event->asset->type) {
                AssetType::Land => SupplyCategory::Land,
                AssetType::Shares, AssetType::ListedGeorgianShares,
                AssetType::ListedGeorgianBonds, AssetType::StateOrIfiBonds => SupplyCategory::Financial,
                default => null,
            };
        }

        return match ($this->event->kind()) {
            IncomeKind::Interest, IncomeKind::Insurance => SupplyCategory::Financial,
            IncomeKind::InternationalTransport => SupplyCategory::ExportTransport,
            IncomeKind::InternationalTelecom => SupplyCategory::Telecom,
            IncomeKind::OilGasSubcontract => SupplyCategory::OilGasOperations,
            IncomeKind::Royalty => SupplyCategory::IntellectualProperty,
            default => null,
        };
    }

    public function is(SupplyCategory ...$categories): bool
    {
        return in_array($this->category(), $categories, true);
    }

    public function usesMarginScheme(): bool
    {
        return $this->event instanceof Sell && $this->event->marginScheme && $this->supplier()->is(Status::TaxableDealer);
    }

    /** Art. 164 — the consideration without VAT; 164¹ for imports; 161².4 for the margin scheme. */
    public function base(): Money
    {
        return match (true) {
            $this->event instanceof Import => $this->event->dutiesGiven() || $this->earlierDuties === null
                ? $this->event->taxableAmount()
                : $this->event->taxableAmount()->plus($this->earlierDuties),
            $this->usesMarginScheme() => $this->event->price->minus($this->event->asset->cost)->clampedToZero()->multipliedBy(1 / self::VAT_FRACTION),
            $this->event instanceof Sell => $this->event->price,
            $this->event instanceof Gift => $this->event->value,
            $this->event instanceof SelfSupply => $this->event->value,
            $this->event instanceof Pay && $this->event->pricesIncludeVat => $this->event->amount->multipliedBy(1 / self::VAT_FRACTION),
            default => $this->event->income(),
        };
    }

    /* ── Outcome builders ──────────────────────────────────── */

    /**
     * VAT is due. $agent is the reverse-charging customer, if any; turnover counts toward the
     * supplier's registration threshold only when the supplier itself charges it.
     *
     * @param list<string> $notes
     */
    public function charged(object $basis, Collection $collection, array $notes = [], ?Party $agent = null): Taxable
    {
        $rate = $this->rates->rate('166', $this->event->date());
        $base = $this->base();
        $chargedBySupplier = $agent === null && !$this->isImport();

        return new Taxable(
            self::TAX, self::cite($basis), $this->supplier(), $base, $rate, $base->percent($rate->percent),
            $collection, $agent, Deadline::of($collection, $this->event->date()), $notes,
            creditableBy: $this->creditor($agent),
            countsAsTurnover: $chargedBySupplier,
        );
    }

    public function exempt(object $basis, string $reason): Exempt
    {
        return new Exempt(self::TAX, self::cite($basis), $reason);
    }

    public function exemptWithCredit(object $basis, string $reason, bool $countsTowardThreshold = false): ExemptWithCredit
    {
        return new ExemptWithCredit(self::TAX, self::cite($basis), $reason, $this->supplier(), $this->base(), $countsTowardThreshold);
    }

    /** Not taxed — optionally still counted toward the supplier's 100,000 GEL threshold. */
    public function notTaxable(object $basis, string $reason, bool $countsAsTurnover = false): NotTaxable
    {
        return new NotTaxable(
            self::TAX, self::cite($basis), $reason,
            $countsAsTurnover ? $this->supplier() : null,
            $countsAsTurnover ? $this->base() : null,
        );
    }

    public function unresolved(object $basis, string $reason): Unresolved
    {
        return new Unresolved(self::TAX, self::cite($basis), $reason);
    }

    /** Art. 174.3, 175 — only a registered VAT payer credits; never on the margin scheme (161².6). */
    private function creditor(?Party $agent): ?Party
    {
        $buyer = $this->isImport() ? $this->supplier() : ($agent ?? $this->customer());

        return $buyer !== null && $buyer->is(Status::VatPayer) && !$this->usesMarginScheme() ? $buyer : null;
    }

    private static function cite(object $basis): Citation
    {
        return $basis instanceof Citation ? $basis : Citation::of($basis);
    }
}
