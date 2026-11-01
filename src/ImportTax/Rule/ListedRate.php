<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Rule;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\ImportTax\Clearance;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * 12% (197.1) or 5% (197.2) of customs value for listed goods — meat, dairy, vegetables, flour,
 * sugar, prepared food, building materials, plastics and the like. Anything not listed is 0%.
 */
#[Article('197.1–2', 'Import tax on customs value')]
final class ListedRate implements Rule
{
    public function assess(Clearance $c): ?Outcome
    {
        return match ($c->table->percentFor($c->commodity)) {
            12 => $c->taxed(new Citation('197.1', '12% list'), 12),
            5 => $c->taxed(new Citation('197.2', '5% list'), 5),
            default => $c->notTaxable(new Citation('197'), "HS {$c->commodity->hsCode} is on no import tax list — 0%."),
        };
    }
}
