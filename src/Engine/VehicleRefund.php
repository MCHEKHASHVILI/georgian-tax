<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use GeorgianTax\Domain\Event\ReexportVehicle;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Law\Citation;
use Mchekhashvili\TaxLegislation\Rate;

/**
 * The refund of a tax paid on a vehicle released into free circulation and exported within 180
 * days (excise 189.8, import tax 198.3): what was paid on the release, at the refund rate the
 * article sets (100%), as a negative `Levied` for the importer, dated the export day.
 */
final class VehicleRefund
{
    /**
     * @param Outcome|null $released what the tax charged on the release, as the pipeline assesses it
     * @param Money|null   $paid     what the importer says was paid, given instead of `$released`
     */
    public static function of(
        string $tax,
        Citation $citation,
        ReexportVehicle $event,
        ?Outcome $released,
        ?Money $paid,
        Rate $rate,
    ): Outcome {
        $on = $event->released->on->format('Y-m-d');

        if ($paid === null) {
            if (!$released instanceof Taxable && !$released instanceof Levied) {
                $why = $released === null ? '' : " ({$released->basis()->ref})";

                return new NotTaxable($tax, $citation, "Nothing was paid on the release on $on$why, so there is nothing to refund.");
            }

            $paid = $released->amount;
            $how = "$paid paid on the release on $on ({$released->basis()->ref})";
        } else {
            $how = "$paid paid on the release on $on, as given";
        }

        if ($paid->isZero()) {
            return new NotTaxable($tax, $citation, "Nothing was paid on the release on $on, so there is nothing to refund.");
        }

        $refund = ($rate->percent >= 100 ? $paid : $paid->percent($rate->percent))->negated();
        $exported = $event->exportedOn->setTime(0, 0);

        return new Levied(
            $tax,
            $citation,
            $event->released->importer,
            1.0,
            'vehicle',
            $refund,
            $refund,
            Collection::AtCustoms,
            $exported,
            [
                "$how: $rate of it refunded ({$citation->ref}).",
                "Placed under the export procedure on {$exported->format('Y-m-d')}, within " . ReexportVehicle::DAYS
                    . " calendar days of the release (last day {$event->lastDay()->format('Y-m-d')}), and left Georgia.",
                'Refunded under the established procedure, which the Code does not hold; dated the export day.',
            ],
        );
    }

    /** Why a vehicle does not qualify: exported too late, or not yet out of Georgia. */
    public static function notInTime(string $tax, Citation $citation, ReexportVehicle $event): NotTaxable
    {
        $reason = !$event->leftGeorgia
            ? 'The vehicle has not left Georgia; the refund needs both the export procedure and the departure.'
            : "Exported on {$event->exportedOn->format('Y-m-d')}, after the last day {$event->lastDay()->format('Y-m-d')} — "
                . ReexportVehicle::DAYS . ' calendar days from the release on ' . $event->released->on->format('Y-m-d') . '.';

        return new NotTaxable($tax, $citation, "No refund. $reason");
    }
}
