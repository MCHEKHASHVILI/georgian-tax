<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\NonResident;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use GeorgianTax\Law\Article;

/** Applies only when an enterprise, organization or entrepreneur pays; otherwise 134.1.ე decides. */
#[Article('134.1.გ', 'International transport or telecom paid to a non-resident')]
final class NonResidentTransportTelecom implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->taxpayer()->isNonResidentWithoutPE()
            || !$a->is(IncomeKind::InternationalTransport, IncomeKind::InternationalTelecom)
            || !$a->payerCanWithhold()) {
            return null;
        }

        return $a->withheld($this, '134.1.გ');
    }
}
