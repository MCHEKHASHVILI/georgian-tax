<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use Closure;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Outcome\Outcome;

/**
 * One tax of the Code: income tax, profit tax, VAT, excise, import, property.
 * As a stage of the engine pipeline it adds its outcome to the report and passes the report on.
 */
abstract class Tax
{
    abstract public function name(): string;

    abstract public function assess(TaxableEvent $event): Outcome;

    /** @param Closure(TaxReport): TaxReport $next */
    final public function __invoke(TaxReport $report, Closure $next): TaxReport
    {
        return $next($report->with($this->assess($report->event)));
    }
}
