<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Withholding;

use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use GeorgianTax\Law\Article;

/**
 * A company, organization or entrepreneur gives property to an individual. Gifts under the
 * 1,000 GEL yearly allowance never reach here (82.1.თ); the withholding method is set by the
 * Minister of Finance.
 */
#[Article('154.1.მ', 'Gift from a tax agent')]
final class GiftFromTaxAgent implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        return $a->event instanceof Gift && $a->payerCanWithhold() && $a->taxpayer()->kind === PartyKind::Individual
            ? $a->withheld($this, '81.1')
            : null;
    }
}
