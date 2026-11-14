<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Stage;

use Closure;
use GeorgianTax\Domain\Event\ReexportVehicle;
use GeorgianTax\Engine\LawTables;
use GeorgianTax\Engine\VehicleRefund;
use GeorgianTax\Excise\Duty;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;
use Mchekhashvili\TaxLegislation\Rates\RateTable;

/**
 * A passenger car (HS 8703) or motorcycle (HS 8711, mopeds included) placed under the export
 * procedure within 180 calendar days of its release into free circulation, and gone from Georgia:
 * the importer may have back the excise paid on it, 100% (189.8). The stages after this one assess
 * the release as it was — its day's excise table and rules, its age, its reliefs — and this one
 * answers with the refund of what that charged, or of `excisePaid` when the importer gives it. The
 * 100% is the rate `189.8`, read on the export day, so an amending law changes it without code.
 *
 * Not modelled: an exemption in force on the release but repealed by the export day is not applied
 * to the release (exemptions stand on the event's day, the export); give `excisePaid` then.
 */
#[Article('189.8', 'Excise refund on a car or motorcycle exported within 180 days')]
final class RefundReexportedVehicle implements Stage
{
    public function __construct(private readonly LawTables $tables, private readonly RateTable $rates) {}

    public function handle(Duty $d, Closure $next): Outcome
    {
        $event = $d->event;

        if (!$event instanceof ReexportVehicle) {
            return $next($d);
        }

        $cite = new Citation('189.8', 'Excise refund on a car or motorcycle exported within 180 days');
        $vehicle = $event->released->commodity;

        if ($vehicle === null) {
            return new Unresolved(Duty::TAX, $cite, 'Whether the excise is refunded depends on the HS code — describe the vehicle with a Commodity.');
        }

        if (!$vehicle->isPassengerCar() && !$vehicle->isMotorcycle()) {
            return new NotTaxable(Duty::TAX, $cite, "Only passenger cars (HS 8703) and motorcycles (HS 8711) get their excise back on export; HS {$vehicle->hsCode} does not.");
        }

        if (!$event->qualifies()) {
            return VehicleRefund::notInTime(Duty::TAX, $cite, $event);
        }

        $on = $event->released->on;
        $released = $event->excisePaid === null
            ? $next(new Duty($event->released, $this->tables->excise($on), $this->tables->exciseRules($on)))
            : null;

        return VehicleRefund::of(Duty::TAX, $cite, $event, $released, $event->excisePaid, $this->rates->rate('189.8', $event->date()));
    }
}
