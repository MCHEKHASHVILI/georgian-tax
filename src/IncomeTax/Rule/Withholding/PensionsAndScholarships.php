<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Withholding;

use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Withheld at 20% by whoever pays:
 *   154.1.ბ  a non-state pension;
 *   154.1.ვ  a non-state scholarship;
 *   154.1.ჟ  a private pension withdrawn before retirement (82.1.ბ⁴.ბ);
 *   154.1.რ  an accumulative pension refund — contributions made in error, on exit, or on emigration (82.1.ბ³ note).
 * State pensions and scholarships never reach here: ExemptByNature releases them (82.1.ბ).
 */
#[Article('154.1.ბ, ვ, ჟ, რ', 'Pensions and scholarships')]
final class PensionsAndScholarships implements Rule
{
    private const ARTICLES = [
        'Pension' => '154.1.ბ',
        'Scholarship' => '154.1.ვ',
        'PrivatePensionEarlyWithdrawal' => '154.1.ჟ',
        'AccumulativePensionRefund' => '154.1.რ',
    ];

    public function assess(Assessment $a): ?Outcome
    {
        $article = self::ARTICLES[$a->kind()->name] ?? null;

        return $article === null ? null : $a->withheld(new Citation($article), '81.1');
    }
}
