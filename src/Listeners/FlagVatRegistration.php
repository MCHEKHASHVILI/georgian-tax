<?php

declare(strict_types=1);

namespace GeorgianTax\Listeners;

use GeorgianTax\Events\VatRegistrationRequired;
use Illuminate\Contracts\Events\Dispatcher;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Events\TaxableEventAssessed;

/** When VAT came out of the 165.1 threshold rule, tells the app the supplier must register. */
final readonly class FlagVatRegistration
{
    public function __construct(private Dispatcher $events) {}

    public function handle(TaxableEventAssessed $assessed): void
    {
        $vat = $assessed->report->for('vat');

        if ($vat instanceof Taxable && $vat->basis()->ref === '165.1') {
            $this->events->dispatch(new VatRegistrationRequired($vat->taxpayer, $vat, $assessed->report));
        }
    }
}
