<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Rule;

use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\ProfitTax\Outflow;
use GeorgianTax\ProfitTax\OutflowKind;

/**
 * An international company pays 5% instead of 15%, on the same objects, grossed up by 0.95
 * (23.9–23.11). Its dividends are free of withholding at the partner's end (23.8).
 */
#[Article('23.10', 'International company')]
final class InternationalCompany implements Rule
{
    public function assess(Outflow $o): ?Outcome
    {
        return $o->company->is(Status::InternationalCompany) && $o->kind !== OutflowKind::Representation
            ? $o->taxed($this, '23.10')
            : null;
    }
}
