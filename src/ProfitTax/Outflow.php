<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax;

use GeorgianTax\Domain\Event\TaxableEvent;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\Engine\Outcome\NotTaxable;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\Outcome\Unresolved;
use GeorgianTax\Law\Citation;
use GeorgianTax\Ledger\CompanyBooks;
use GeorgianTax\Ledger\YearToDate;
use GeorgianTax\Rates\RateTable;

/**
 * The subject of the profit tax pipeline: value leaving a company. Georgia taxes profit when it is
 * distributed, not when it is earned (the Estonian model), so the company *paying* is the taxpayer.
 * IdentifyOutflow fills in the company, the kind and the amount.
 */
final readonly class Outflow
{
    public const TAX = 'profit';

    public function __construct(
        public TaxableEvent $event,
        public YearToDate $ledger,
        public RateTable $rates,
        public CompanyBooks $books,
        public ?Party $company = null,
        public ?OutflowKind $kind = null,
        public ?Money $amount = null,
    ) {}

    public function as(Party $company, OutflowKind $kind, Money $amount): self
    {
        return new self($this->event, $this->ledger, $this->rates, $this->books, $company, $kind, $amount);
    }

    public function year(): int
    {
        return (int) $this->event->date()->format('Y');
    }

    public function isOrganization(): bool
    {
        return $this->company?->kind === PartyKind::Organization;
    }

    /**
     * Tax the outflow: the amount paid out is net of tax, so the base is grossed up — divided by
     * 0.85 at 15% (97.10), by 0.95 at the international company's 5% (23.11).
     *
     * @param list<string> $notes
     */
    public function taxed(object $basis, string $rateArticle, ?Money $amount = null, array $notes = []): Taxable
    {
        $rate = $this->rates->rate($rateArticle, $this->event->date());
        $paid = $amount ?? $this->amount;
        $base = $paid->dividedBy((100 - $rate->percent) / 100);
        $collection = Collection::MonthlyDeclaration;

        return new Taxable(
            self::TAX, self::cite($basis), $this->company, $base, $rate, $base->percent($rate->percent),
            $collection, null, $collection->deadline($this->event->date()),
            ["$paid paid out, grossed up to $base (÷ " . ((100 - $rate->percent) / 100) . ').', ...$notes],
        );
    }

    public function exempt(object $basis, string $reason): Exempt
    {
        return new Exempt(self::TAX, self::cite($basis), $reason);
    }

    public function notTaxable(object $basis, string $reason): NotTaxable
    {
        return new NotTaxable(self::TAX, self::cite($basis), $reason);
    }

    public function unresolved(object $basis, string $reason): Unresolved
    {
        return new Unresolved(self::TAX, self::cite($basis), $reason);
    }

    private static function cite(object $basis): Citation
    {
        return $basis instanceof Citation ? $basis : Citation::of($basis);
    }
}
