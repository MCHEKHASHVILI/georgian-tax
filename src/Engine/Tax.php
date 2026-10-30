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

    /**
     * Assess with sight of what the taxes earlier in the pipeline decided. Most taxes don't need it;
     * VAT does, because excise and import tax form part of the VAT base on import (Art. 164¹.2.ა).
     */
    public function assessIn(TaxReport $report): Outcome
    {
        return $this->assess($report->event);
    }

    /** @param Closure(TaxReport): TaxReport $next */
    final public function __invoke(TaxReport $report, Closure $next): TaxReport
    {
        return $next($report->with($this->assessIn($report)));
    }
}
