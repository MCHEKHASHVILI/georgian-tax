<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Source;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\IncomeTax\Assessment;
use Mchekhashvili\TaxLegislation\Law\Article;

/** International transport and telecom between Georgia and abroad (Art. 8.25 excludes foreign-only legs). */
#[Article('104.1.ჟ', 'International transport and telecom')]
final class InternationalCarriage implements SourceRule
{
    public function decide(Assessment $a): ?bool
    {
        return $a->is(IncomeKind::InternationalTransport, IncomeKind::InternationalTelecom) ? true : null;
    }
}
