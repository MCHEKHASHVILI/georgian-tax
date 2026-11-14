<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Stage;

use Closure;
use GeorgianTax\Domain\Event\ReexportVehicle;
use GeorgianTax\Engine\LawTables;
use GeorgianTax\Engine\VehicleRefund;
use GeorgianTax\ImportTax\Clearance;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * A passenger car (HS 8703) placed under the export procedure within 180 calendar days of its
 * release into free circulation, and gone from Georgia: the importer may have back the import tax
 * paid on it, 100% (198.3). Unlike excise (189.8), motorcycles are not included. The stages after
 * this one assess the release as it was — its day's import tables, the car's age, its relief — and
 * this one answers with the refund of what that charged, or of `importTaxPaid` when the importer
 * gives it. The 100% is the rate `198.3`, read on the export day.
 *
 * Not modelled: an exemption in force on the release but repealed by the export day is not applied
 * to the release (exemptions stand on the event's day, the export); give `importTaxPaid` then.
 */
#[Article('198.3', 'Import tax refund on a car exported within 180 days')]
final class RefundReexportedCar implements Stage
{
    public function __construct(private readonly LawTables $tables) {}

    public function handle(Clearance $c, Closure $next): Outcome
    {
        $event = $c->event;

        if (!$event instanceof ReexportVehicle) {
            return $next($c);
        }

        $cite = new Citation('198.3', 'Import tax refund on a car exported within 180 days');
        $vehicle = $event->released->commodity;

        if ($vehicle === null) {
            return $c->unresolved($cite, 'Whether the import tax is refunded depends on the HS code — describe the vehicle with a Commodity.');
        }

        if (!$vehicle->isPassengerCar()) {
            return $c->notTaxable($cite, "Only passenger cars (HS 8703) get their import tax back on export; HS {$vehicle->hsCode} does not.");
        }

        if (!$event->qualifies()) {
            return VehicleRefund::notInTime(Clearance::TAX, $cite, $event);
        }

        $released = $event->importTaxPaid === null
            ? $next(new Clearance($event->released, $this->tables->import($event->released->on), $c->thresholds, $c->rates))
            : null;

        return VehicleRefund::of(Clearance::TAX, $cite, $event, $released, $event->importTaxPaid, $c->rate('198.3'));
    }
}
