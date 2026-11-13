<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Stage;

use Closure;
use GeorgianTax\Domain\Event\AcquireForMaturing;
use GeorgianTax\Engine\Deadline;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Excise\Operation;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Whoever imports or buys excise goods for maturing may credit the excise paid or payable on them
 * (189.5). The credit comes with the acquisition and, unlike 189.1's, is not capped at the excise
 * on the goods made from them; 189.3's documents are required only for 189.1–2. The outcome is the
 * credit as a negative `Levied`, for the month of the acquisition — the excise reporting period
 * (190.1) — with the date of that month's payment (190.2).
 *
 * Not modelled: taking a refund instead of the credit (189.5).
 */
#[Article('189.5', 'Excise credit for goods acquired for maturing')]
final class CreditMaturing implements Stage
{
    public function handle(Duty $d, Closure $next): Outcome
    {
        $event = $d->event;

        if ($d->operation !== Operation::Maturing || !$event instanceof AcquireForMaturing) {
            return $next($d);
        }

        $credit = $event->excise->negated();
        $how = $event->imported ? 'imported' : 'bought';

        return new Levied(
            Duty::TAX,
            new Citation('189.5', 'Excise credit for goods acquired for maturing'),
            $d->taxpayer,
            1.0,
            'acquisition',
            $credit,
            $credit,
            Collection::MonthlyDeclaration,
            Deadline::of(Collection::MonthlyDeclaration, $event->date()),
            [
                "Excise of {$event->excise} paid or payable on {$event->description} (HS {$event->commodity->hsCode}), $how for maturing: credited in full, without the 189.1 cap (189.5).",
            ],
        );
    }
}
