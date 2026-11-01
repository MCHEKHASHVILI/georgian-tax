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
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * The employer withholds 20%. Three employers are carved out:
 *   a free-industrial-zone company paying a resident — Government resolution decides (154.1.ა.ა, 153.9);
 *   an international organization with diplomatic status — Minister of Finance decides (153.8);
 *   a foreign employer without a Georgian PE — the employee pays (154.1.ა.ბ, 154.5).
 */
#[Article('154.1.ა', 'Salary')]
final class Salary implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->is(IncomeKind::Salary)) {
            return null;
        }

        $employer = $a->payer();

        if ($a->payerIs(Status::FreeIndustrialZone)) {
            return $a->unresolved(new Citation('153.9'), "Salary from a free-industrial-zone company is taxed under a Government resolution.");
        }

        if ($a->payerIs(Status::DiplomaticMission)) {
            return $a->unresolved(new Citation('153.8'), 'Pay from an international organization with diplomatic status follows a Minister of Finance order.');
        }

        if ($employer !== null && !$employer->isResident() && !$employer->hasGeorgianPE && !$a->payment()?->chargedToGeorgianPE) {
            return $a->declared($this, '81.1', Collection::MonthlyDeclaration, [
                'A foreign employer without a Georgian PE does not withhold (154.1.ა.ბ); the employee pays (154.5).',
            ]);
        }

        return $a->withheld($this, '81.1');
    }
}
