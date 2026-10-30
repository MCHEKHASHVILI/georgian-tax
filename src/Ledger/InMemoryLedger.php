<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger;

use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Inherit;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\TaxReport;

final class InMemoryLedger implements YearToDate
{
    /** @var list<array{recipient: string, payer: ?string, kind: IncomeKind, year: int, income: Money, heir: ?HeirOrder, taxableBase: Money}> */
    private array $entries = [];

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
        $total = Money::zero();

        foreach ($this->entries as $e) {
            if ($e['recipient'] === $taxpayer->id && $e['year'] === $year) {
                $total = $total->plus($e['taxableBase']);
            }
        }

        return $total;
    }

    public function record(TaxableEvent $event, TaxReport $report): void
    {
        $income = $report->for('income');

        $this->entries[] = [
            'recipient' => $event->recipient()->id,
            'payer' => $event->payer()?->id,
            'kind' => $event->kind(),
            'year' => (int) $event->date()->format('Y'),
            'income' => $event->income(),
            'heir' => $event instanceof Gift || $event instanceof Inherit ? $event->relation : null,
            'taxableBase' => $income instanceof Taxable ? $event->income() : Money::zero(), // before allowances
        ];
    }

    /** Seed history, e.g. revenue earned before the engine was introduced. */
    public function seed(Party $recipient, ?Party $payer, IncomeKind $kind, int $year, Money $income): void
    {
        $this->entries[] = [
            'recipient' => $recipient->id, 'payer' => $payer?->id, 'kind' => $kind,
            'year' => $year, 'income' => $income, 'heir' => null, 'taxableBase' => Money::zero(),
        ];
    }

    private function matches(array $entry, array $kinds): bool
    {
        return $kinds === [] || in_array($entry['kind'], $kinds, true);
    }

    private function sum(callable $filter): Money
    {
        $total = Money::zero();

        foreach ($this->entries as $entry) {
            if ($filter($entry)) {
                $total = $total->plus($entry['income']);
            }
        }

        return $total;
    }
}
