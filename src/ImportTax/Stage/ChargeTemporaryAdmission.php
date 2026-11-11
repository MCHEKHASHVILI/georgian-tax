<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Stage;

use Closure;
use GeorgianTax\Domain\Event\TemporaryAdmission;
use GeorgianTax\Engine\Deadline;
use GeorgianTax\ImportTax\Clearance;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Goods admitted temporarily pay, for each full or partial calendar month in Georgia from the day
 * the declaration was registered, 3% of the import tax their import would have charged on that day
 * (197.4) — at most that whole amount (197.5). The stages after this one assess that import (its
 * exemptions, 199.კ–კ² among them, and its rate), and this one takes the share; an import that
 * would pay nothing — exempt, or at 0% — is passed on as it is. The 3% is the rate `197.4`, so an
 * amending law changes it without a code change.
 *
 * Each month's share is due by the 15th of the next month and the last on the day the procedure
 * ends; the declarant may pay it all at once (197.4). The outcome is the total for the months
 * counted, due on the first of those days.
 */
#[Article('197.4–5', 'Import tax on temporary admission')]
final class ChargeTemporaryAdmission implements Stage
{
    public function handle(Clearance $c, Closure $next): Outcome
    {
        $outcome = $next($c);
        $admission = $c->event;

        if (!$admission instanceof TemporaryAdmission || !($outcome instanceof Taxable || $outcome instanceof Levied)) {
            return $outcome;
        }

        $rate = $c->rate('197.4');
        $months = $admission->months();
        $full = $outcome->amount;
        $share = $rate->percent * $months;
        $capped = $share >= 100;
        $amount = $capped ? $full : $full->percent($share);

        $from = $admission->registeredOn();
        $firstDue = Deadline::of(Collection::MonthlyDeclaration, $from);
        $due = $admission->ended && $admission->until < $firstDue ? $admission->until->setTime(0, 0) : $firstDue;

        $plural = $months === 1 ? '' : 's';

        return new Levied(
            Clearance::TAX,
            $capped ? new Citation('197.5', 'Temporary admission, capped at the import tax on release') : new Citation('197.4', 'Import tax on temporary admission'),
            $outcome->taxpayer,
            $months,
            'month',
            $full->percent($rate->percent),
            $amount,
            Collection::AtCustoms,
            $due,
            [
                "Import tax on release on {$from->format('Y-m-d')} would be $full ({$outcome->basis()}).",
                "$rate of it for each of $months calendar month$plural from {$from->format('Y-m-d')} to {$admission->until->format('Y-m-d')} (197.4).",
                ...($capped ? ["Capped at the import tax on release (197.5)."] : []),
                'Each month is due by the 15th of the next, the last on the day the procedure ends; it may all be paid at once (197.4).',
            ],
        );
    }
}
