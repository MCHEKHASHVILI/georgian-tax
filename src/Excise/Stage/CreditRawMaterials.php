<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Stage;

use Closure;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Goods\ExciseInput;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Excise\Operation;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * A producer supplying excise goods credits the excise paid or payable on the excise goods it made
 * them from — bought from their producer or imported (189.1–2) — but never more than the excise on
 * the goods supplied (189.1). Only what a tax invoice, customs declaration or, for a 190¹ sale, the
 * excise declaration proves counts (189.3, 189.3¹); raw materials that made excise and non-excise
 * goods together count in proportion to the excise goods (189.4). The stages after this one work
 * out the excise on the goods, and this one takes the credit off; an exempt supply passes as it is.
 *
 * The outcome is the excise left to pay, with the credit in the notes. What exceeds the cap is not
 * credited on this supply. Not modelled here: the credit on exports (189.1, the export being exempt
 * with credit under 194.6.ა) and taking a refund instead of the credit (189.1). Goods bought for
 * maturing are credited on their acquisition instead (189.5, `CreditMaturing`).
 */
#[Article('189.1–4', 'Excise credit for excise raw materials')]
final class CreditRawMaterials implements Stage
{
    public function handle(Duty $d, Closure $next): Outcome
    {
        $outcome = $next($d);
        $event = $d->event;

        if ($d->operation !== Operation::ProducerSupply || !$event instanceof Pay || $event->exciseInputs === []
            || !$outcome instanceof Levied) {
            return $outcome;
        }

        $full = $outcome->amount;
        $credit = Money::zero($full->currency);
        $notes = [];

        foreach ($event->exciseInputs as $input) {
            /** @var ExciseInput $input */
            $notes[] = self::describe($input);
            $credit = $credit->plus($input->creditable());
        }

        if ($credit->isZero()) {
            return new Levied($outcome->taxName, $outcome->citation, $outcome->taxpayer, $outcome->quantity, $outcome->unit,
                $outcome->ratePerUnit, $outcome->amount, $outcome->collection, $outcome->due, [...$outcome->notes, ...$notes]);
        }

        $capped = $credit->greaterThan($full);
        $credited = $capped ? $full : $credit;

        return new Levied(
            $outcome->taxName,
            new Citation('189.1', 'Excise less the credit for excise raw materials'),
            $outcome->taxpayer,
            $outcome->quantity,
            $outcome->unit,
            $outcome->ratePerUnit,
            $full->minus($credited),
            $outcome->collection,
            $outcome->due,
            [
                ...$outcome->notes,
                "Excise on the goods supplied: $full ({$outcome->citation->ref}).",
                ...$notes,
                $capped
                    ? "Credit $credit capped at the excise on the goods supplied, $full (189.1); the rest is not credited on this supply."
                    : "Less the credit for excise raw materials: $credited (189.1).",
            ],
        );
    }

    private static function describe(ExciseInput $input): string
    {
        if ($input->evidence === null) {
            return "No credit for the excise of {$input->excise} on {$input->description}: no tax invoice or customs declaration proves it was paid (189.3).";
        }

        $share = $input->exciseShare === 1.0 ? '' : sprintf(', %s%% of it for the excise goods made from them (189.4)',
            rtrim(rtrim(number_format($input->exciseShare * 100, 2, '.', ''), '0'), '.'));

        return "Excise of {$input->excise} on {$input->description}, by {$input->evidence->describe()} ({$input->evidence->value})$share: credit {$input->creditable()}.";
    }
}
