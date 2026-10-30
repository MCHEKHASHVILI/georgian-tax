<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Pipeline\Pipeline;
use GeorgianTax\Ledger\YearToDate;

/**
 * The outer pipeline: one event flows through every registered tax.
 *
 *   $engine = new TaxEngine($ledger, IncomeTax::standard($ledger, $rates), $vat, $profitTax);
 *   $report = $engine->process(new Pay($company, $person, IncomeKind::Salary, Money::gel(3000), $today));
 */
final readonly class TaxEngine
{
    private Pipeline $pipeline;

    public function __construct(
        private YearToDate $ledger,
        Tax ...$taxes,
    ) {
        $this->pipeline = new Pipeline(array_values($taxes), static fn (TaxReport $report) => $report);
    }

    public function process(TaxableEvent $event): TaxReport
    {
        $report = $this->pipeline->process(new TaxReport($event));

        $this->ledger->record($event, $report);

        return $report;
    }
}
