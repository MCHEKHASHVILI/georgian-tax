<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Stage;

use Closure;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Excise\Operation;
use GeorgianTax\Excise\Rate\Calls;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;

/**
 * Who pays excise and on what (Art. 182–187). Excise is charged once, at the start of the chain:
 * the producer's supply, the import, the export — plus gas sold for vehicles, call termination and
 * an importer's onward supply of raw tobacco. A shop reselling excise goods pays none.
 */
#[Article('182–184', 'Excise taxpayer and taxable operations')]
final class IdentifyOperation implements Stage
{
    public function __invoke(Duty $d, Closure $next): Outcome
    {
        $event = $d->event;
        $commodity = $event instanceof Import || $event instanceof Pay ? $event->commodity : null;

        if ($commodity === null) {
            return $d->notTaxable(new Citation('183'), 'No excise goods are involved.');
        }

        if (!$this->isExcisable($d, $commodity)) {
            return $d->notTaxable(new Citation('188'), "HS {$commodity->hsCode} is not excise goods.");
        }

        if ($event instanceof Import) {
            return $next($d->as($commodity, $event->importer, Operation::Import));
        }

        $supplier = $event->payee;

        return match (true) {
            $commodity->hsCode === Calls::MOBILE || $commodity->hsCode === Calls::FIXED
                => $next($d->as($commodity, $supplier, Operation::CallTermination)),
            $commodity->startsWith('271121') => $next($d->as($commodity, $supplier, Operation::GasForVehicles)),
            $supplier->is(Status::ExciseProducer) && $event->place()->isExport()
                => $next($d->as($commodity, $supplier, Operation::Export)),
            $supplier->is(Status::ExciseProducer) => $next($d->as($commodity, $supplier, Operation::ProducerSupply)),
            $this->isRawTobacco($commodity) => $next($d->as($commodity, $supplier, Operation::RawTobaccoSupply)),
            default => $d->notTaxable($this, 'Excise is charged on production, import and export; a reseller pays none.'),
        };
    }

    private function isExcisable(Duty $d, Commodity $c): bool
    {
        return $d->table->find($c) !== null
            || $c->isPassengerCar() || $c->isMotorcycle()
            || $c->startsWith('2402') || $c->startsWith('240411')
            || $c->startsWith('2203') || $c->startsWith('2204') || $c->startsWith('2205')
            || $c->startsWith('2206') || $c->startsWith('2207') || $c->startsWith('2208')
            || in_array($c->hsCode, [Calls::MOBILE, Calls::FIXED], true);
    }

    /** 182.1.ზ — an importer supplying the raw tobacco it imported. */
    private function isRawTobacco(Commodity $c): bool
    {
        return $c->startsWith('2401') || $c->startsWith('240311') || $c->startsWith('240319') || $c->startsWith('240391');
    }
}
