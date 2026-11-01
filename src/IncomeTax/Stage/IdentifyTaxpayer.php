<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Stage;

use Closure;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Party\PartyKind;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Income tax is paid by individuals (Art. 79). Companies pay profit tax (Art. 96),
 * except that a non-resident without a Georgian PE has tax withheld under Art. 134.
 */
#[Article('79', 'Income taxpayer')]
final class IdentifyTaxpayer implements Stage
{
    public function handle(Assessment $a, Closure $next): Outcome
    {
        $taxpayer = $a->taxpayer();

        if ($taxpayer->kind === PartyKind::State) {
            return $a->notTaxable(new Citation('130.6, 131.7, 132.4'), 'Payments to the state are not taxed at source.');
        }

        if (!$taxpayer->isIndividual() && $taxpayer->isResident()) {
            return $a->notTaxable(new Citation('96', 'Profit taxpayer'), 'A resident company or organization pays profit tax, not income tax.');
        }

        if (!$taxpayer->isIndividual() && $taxpayer->hasGeorgianPE) {
            return $a->notTaxable(new Citation('134.1'), 'Income of a registered Georgian PE is taxed through the PE, not withheld.');
        }

        if (!$a->income()->isPositive() && !($a->event instanceof Sell)) {
            return $a->notTaxable($this, 'The event creates no income.');
        }

        if (!$a->income()->isPositive()) {
            return $a->notTaxable(new Citation('82.4.ბ', 'Gain on a sale'), 'The sale price does not exceed the cost, so there is no gain.');
        }

        return $next($a);
    }
}
