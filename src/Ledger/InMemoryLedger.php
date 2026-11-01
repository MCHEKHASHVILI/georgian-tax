<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger;

use DateTimeImmutable;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Engine\TaxReport;

final class InMemoryLedger implements YearToDate
{
    /** @var list<array{recipient: string, payer: ?string, kind: IncomeKind, year: int, income: Money, heir: ?HeirOrder, taxableBase: Money}> */
    private array $entries = [];

    /** @var list<array{party: string, tax: string, date: DateTimeImmutable, amount: Money}> */
    private array $turnover = [];

    /** @var array<string, Money> keyed "company|year|category" */
    private array $spending = [];

    public function received(Party $recipient, int $year, IncomeKind ...$kinds): Money
    {
        return $this->sum(fn ($e) => $e['recipient'] === $recipient->id && $e['year'] === $year && $this->matches($e, $kinds));
    }

    public function receivedFrom(Party $recipient, Party $payer, int $year, IncomeKind ...$kinds): Money
    {
        return $this->sum(fn ($e) => $e['recipient'] === $recipient->id && $e['payer'] === $payer->id
            && $e['year'] === $year && $this->matches($e, $kinds));
    }

    public function receivedFromHeirs(Party $recipient, int $year, HeirOrder ...$orders): Money
    {
        return $this->sum(fn ($e) => $e['recipient'] === $recipient->id && $e['year'] === $year
            && $e['heir'] !== null && in_array($e['heir'], $orders, true));
    }

    public function paid(Party $payer, int $year, IncomeKind ...$kinds): Money
    {
        return $this->sum(fn ($e) => $e['payer'] === $payer->id && $e['year'] === $year && $this->matches($e, $kinds));
    }

    public function taxableIncome(Party $taxpayer, int $year): Money
    {
        $total = Money::GEL(0);

        foreach ($this->entries as $e) {
            if ($e['recipient'] === $taxpayer->id && $e['year'] === $year) {
                $total = $total->plus($e['taxableBase']);
            }
        }

        return $total;
    }

    public function turnover(Party $party, string $tax, DateTimeImmutable $since): Money
    {
        $total = Money::GEL(0);

        foreach ($this->turnover as $t) {
            if ($t['party'] === $party->id && $t['tax'] === $tax && $t['date'] >= $since) {
                $total = $total->plus($t['amount']);
            }
        }

        return $total;
    }

    public function spent(Party $company, int $year, string $category): Money
    {
        return $this->spending["{$company->id}|$year|$category"] ?? Money::GEL(0);
    }

    public function record(TaxableEvent $event, TaxReport $report): void
    {
        $entries = LedgerEntries::for($event, $report);

        if ($entries->income !== null) {
            $this->entries[] = $entries->income;
        }

        foreach ($entries->turnover as $line) {
            $this->turnover[] = ['party' => $line['party'], 'tax' => $line['tax'], 'date' => $line['on'], 'amount' => $line['amount']];
        }

        if (($spend = $entries->spending) !== null) {
            $key = "{$spend['company']}|{$spend['year']}|{$spend['category']}";
            $this->spending[$key] = ($this->spending[$key] ?? Money::GEL(0))->plus($spend['amount']);
        }
    }

    /** Seed turnover a tax counted before the engine was introduced. */
    public function seedTurnover(Party $party, string $tax, DateTimeImmutable $on, Money $amount): void
    {
        $this->turnover[] = ['party' => $party->id, 'tax' => $tax, 'date' => $on, 'amount' => $amount];
    }

    /** Seed history, e.g. revenue earned before the engine was introduced. */
    public function seed(Party $recipient, ?Party $payer, IncomeKind $kind, int $year, Money $income): void
    {
        $this->entries[] = [
            'recipient' => $recipient->id, 'payer' => $payer?->id, 'kind' => $kind,
            'year' => $year, 'on' => new DateTimeImmutable("$year-01-01"), 'income' => $income, 'heir' => null, 'taxableBase' => Money::GEL(0),
        ];
    }

    private function matches(array $entry, array $kinds): bool
    {
        return $kinds === [] || in_array($entry['kind'], $kinds, true);
    }

    private function sum(callable $filter): Money
    {
        $total = Money::GEL(0);

        foreach ($this->entries as $entry) {
            if ($filter($entry)) {
                $total = $total->plus($entry['income']);
            }
        }

        return $total;
    }
}
