<?php

declare(strict_types=1);

namespace GeorgianTax\Events;

use GeorgianTax\Engine\TaxReport;

/** Every tax has assessed an event and the ledger has recorded it. */
final readonly class TaxableEventAssessed
{
    public function __construct(public TaxReport $report) {}
}
