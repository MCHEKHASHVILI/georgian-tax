<?php

declare(strict_types=1);

namespace GeorgianTax\Events;

use Mchekhashvili\TaxLegislation\Contracts\Taxpayer;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;

/**
 * An unregistered supplier's taxable turnover over 12 months just passed 100,000 GEL (165.1). VAT
 * applies from this supply on, and the supplier must apply to register within 2 working days.
 */
final readonly class VatRegistrationRequired
{
    public function __construct(
        public Taxpayer $supplier,
        public Taxable $vat,
        public TaxReport $report,
    ) {}

    /** The last day to apply: 2 working days after the supply (165.1). */
    public function deadline(): \DateTimeImmutable
    {
        $day = $this->report->event->date();

        for ($left = 2; $left > 0;) {
            $day = $day->modify('+1 day');
            $left -= (int) $day->format('N') <= 5 ? 1 : 0;
        }

        return $day;
    }
}
