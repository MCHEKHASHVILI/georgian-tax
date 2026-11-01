<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax;

use GeorgianTax\Engine\Deadline;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Law\Citation;
use GeorgianTax\Ledger\YearToDate;
use Mchekhashvili\TaxLegislation\Rates\RateTable;
use Mchekhashvili\TaxLegislation\Rates\ThresholdTable;

/**
 * The subject that flows through the income tax stages: the event plus what the stages
 * have established about it. Stages and rules finish it with one of the outcome builders,
 * which is what keeps every rule a few readable lines.
 */
final readonly class Assessment
{
    public const TAX = 'income';

    public function __construct(
        public TaxableEvent $event,
        public YearToDate $ledger,
        public RateTable $rates,
        public ThresholdTable $thresholds,
        public ?bool $georgianSource = null,
    ) {}

    /** An income tax threshold as it stood on the event's day (resources/law/thresholds.php or the database). */
    public function threshold(string $key): Money
    {
        return $this->thresholds->threshold(self::TAX, $key, $this->event->date());
    }

    /* ── Facts ─────────────────────────────────────────────── */

    public function taxpayer(): Party { return $this->event->recipient(); }
    public function payer(): ?Party { return $this->event->payer(); }
    public function kind(): IncomeKind { return $this->event->kind(); }
    public function income(): Money { return $this->event->income(); }
    public function year(): int { return (int) $this->event->date()->format('Y'); }

    public function is(IncomeKind ...$kinds): bool
    {
        return in_array($this->kind(), $kinds, true);
    }

    /** The Pay event, when this is one — for rules that read payment-specific facts. */
    public function payment(): ?Pay
    {
        return $this->event instanceof Pay ? $this->event : null;
    }

    public function payerIs(Status $status): bool
    {
        return $this->payer()?->is($status) ?? false;
    }

    /** Art. 154.1 — the payer can act as a tax agent. */
    public function payerCanWithhold(): bool
    {
        return $this->payer()?->kind->canWithhold() ?? false;
    }

    public function before(string $date): bool
    {
        return $this->event->date() < new \DateTimeImmutable($date);
    }

    public function withGeorgianSource(bool $georgianSource): self
    {
        return new self($this->event, $this->ledger, $this->rates, $this->thresholds, $georgianSource);
    }

    /* ── Outcome builders ──────────────────────────────────── */

    /**
     * The payer withholds. If the payer is not a tax agent (a private individual), the same
     * liability falls to the recipient's annual declaration instead (Art. 153.1.ა, 153.1.გ).
     *
     * @param list<string> $notes
     */
    public function withheld(object $rule, string $rateArticle, array $notes = []): Taxable
    {
        if (!$this->payerCanWithhold()) {
            return $this->declared($rule, $rateArticle, Collection::AnnualDeclaration, [
                ...$notes, 'Payer is not a tax agent (Art. 154.1), so the recipient declares (Art. 153.1).',
            ]);
        }

        return $this->taxable(self::cite($rule), $rateArticle, Collection::WithheldAtSource, $this->payer(), $notes);
    }

    /** @param list<string> $notes */
    public function declared(object $rule, string $rateArticle, Collection $collection, array $notes = []): Taxable
    {
        return $this->taxable(self::cite($rule), $rateArticle, $collection, null, $notes);
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

    /**
     * Art. 134.1¹ — a payee registered in a preferential-tax country pays 15% instead of the
     * 134.1.ბ (interest), ბ¹ (royalty) and ე (other income) rates.
     */
    public function nonResidentRate(string $rateArticle): string
    {
        $covered = in_array($rateArticle, ['131.1', '134.1.ბ¹', '134.1.ე'], true);

        return $covered && $this->taxpayer()->is(Status::PreferentialTaxJurisdiction) ? '134.1¹' : $rateArticle;
    }

    private static function cite(object $basis): Citation
    {
        return $basis instanceof Citation ? $basis : Citation::of($basis);
    }

    /** @param list<string> $notes */
    private function taxable(Citation $basis, string $rateArticle, Collection $collection, ?Party $agent, array $notes): Taxable
    {
        $rate = $this->rates->rate($rateArticle, $this->event->date());
        $base = $this->income();

        if ($rateArticle !== $basis->ref) {
            $notes = ["Rate set by Art. $rateArticle.", ...$notes];
        }

        return new Taxable(
            self::TAX, $basis, $this->taxpayer(), $base, $rate, $base->percent($rate->percent),
            $collection, $agent, Deadline::of($collection, $this->event->date()), $notes,
        );
    }
}
