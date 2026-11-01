<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Stage;

use Closure;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\Vat\Place\PlaceRule;
use GeorgianTax\Vat\Supply;

/**
 * Art. 162–162¹: is the supply made in Georgia? The first rule with an opinion wins. A supply made
 * abroad carries no Georgian VAT, though the supplier may still credit related input VAT (175.2.ა).
 */
#[Article('162, 162¹', 'Place of supply')]
final readonly class ResolvePlaceOfSupply implements Stage
{
    /** @param list<PlaceRule> $handlers */
    public function __construct(private array $handlers) {}

    public function handle(Supply $s, Closure $next): Outcome
    {
        if ($s->isImport()) {
            return $next($s);
        }

        foreach ($this->handlers as $rule) {
            $inGeorgia = $rule->inGeorgia($s);

            if ($inGeorgia === true) {
                return $next($s);
            }

            if ($inGeorgia === false) {
                return $s->notTaxable($rule, 'Supplied outside Georgia; the supplier may still credit related input VAT (175.2.ა).');
            }
        }

        return $next($s);
    }
}
