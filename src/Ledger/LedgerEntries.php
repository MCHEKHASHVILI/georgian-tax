<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger;

use DateTimeImmutable;
use GeorgianTax\Domain\Event\Expense;
use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Inherit;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\CountsAsTurnover;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use Mchekhashvili\Money\Money;

/**
 * What one assessed event adds to the year-to-date ledger — the same rows whichever store keeps
 * them: an income line, turnover lines per tax, and a company's spending by category.
 */
final readonly class LedgerEntries
{
    /**
     * @param array{recipient: string, payer: ?string, kind: IncomeKind, year: int, on: DateTimeImmutable, income: Money, heir: ?HeirOrder, taxableBase: Money}|null $income
     * @param list<array{party: string, tax: string, on: DateTimeImmutable, amount: Money}> $turnover
     * @param array{company: string, year: int, category: string, amount: Money}|null $spending
     */
    public function __construct(
        public ?array $income,
        public array $turnover,
        public ?array $spending,
    ) {}

    public static function for(TaxableEvent $event, TaxReport $report): self
    {
        $on = $event->date();
        $year = (int) $on->format('Y');

        $turnover = [];
        foreach ($report->outcomes as $outcome) {
            if ($outcome instanceof CountsAsTurnover && $outcome->turnoverOf() !== null && $outcome->turnover()?->isZero() === false) {
                $turnover[] = ['party' => $outcome->turnoverOf()->id, 'tax' => $outcome->tax(), 'on' => $on, 'amount' => $outcome->turnover()];
            }
        }

        $taxedAsIncome = $report->for('income') instanceof Taxable;
        $income = $event->income()->isZero() ? null : [
            'recipient' => $event->recipient()->id,
            'payer' => $event->payer()?->id,
            'kind' => $event->kind(),
            'year' => $year,
            'on' => $on,
            'income' => $event->income(),
            'heir' => $event instanceof Gift || $event instanceof Inherit ? $event->relation : null,
            'taxableBase' => $taxedAsIncome ? $event->income() : Money::GEL(0),   // before allowances
        ];

        $spending = match (true) {
            $event instanceof Expense => ['company' => $event->company->id, 'year' => $year, 'category' => $event->nature->value, 'amount' => $event->amount],
            $event instanceof Gift && $event->recipient->is(Status::CharityOrganization)
                => ['company' => $event->donor->id, 'year' => $year, 'category' => 'charity', 'amount' => $event->value],
            default => null,
        };

        return new self($income, $turnover, $spending);
    }
}
