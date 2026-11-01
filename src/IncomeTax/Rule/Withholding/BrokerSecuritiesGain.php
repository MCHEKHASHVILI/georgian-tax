<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Withholding;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;

/** A brokerage pays out the gain on securities it sold for someone not registered as a taxpayer. */
#[Article('154.1.ლ', 'Securities gain paid out by a broker')]
final class BrokerSecuritiesGain implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->is(IncomeKind::SecuritiesGainViaBroker)) {
            return null;
        }

        return $a->taxpayer()->is(Status::RegisteredTaxpayer)
            ? $a->declared($this, '81.1', Collection::MonthlyDeclaration, ['Registered taxpayers declare the gain themselves (153.12).'])
            : $a->withheld($this, '81.1');
    }
}
