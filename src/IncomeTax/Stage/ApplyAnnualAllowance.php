<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Stage;

use Closure;
use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Relief;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use GeorgianTax\IncomeTax\Assessment;
use Mchekhashvili\TaxLegislation\Law\Article;

/**
 * Art. 82.2–82.3 — veterans, single parents, people with disabilities and others have an annual
 * tax-free amount. Only the highest allowance applies. Runs *after* the rules, reducing the base
 * of whatever they decided while the allowance lasts.
 */
#[Article('82.2', 'Annual personal allowance')]
final class ApplyAnnualAllowance implements Stage
{
    public function handle(Assessment $a, Closure $next): Outcome
    {
        $outcome = $next($a);

        if (!$outcome instanceof Taxable || !$outcome->base->isPositive() || !$a->taxpayer()->isResident()) {
            return $outcome;
        }

        $relief = $this->highestRelief($a);

        if ($relief === null) {
            return $outcome;
        }

        $remaining = $relief->annualAllowance()->minus($a->ledger->taxableIncome($a->taxpayer(), $a->year()))->clampedToZero();

        if ($remaining->isZero()) {
            return $outcome;
        }

        $sheltered = Money::min($remaining, $outcome->base);

        return $outcome->withBase(
            $outcome->base->minus($sheltered),
            "Art. {$relief->article()}: $sheltered sheltered by the annual allowance of {$relief->annualAllowance()}.",
        );
    }

    private function highestRelief(Assessment $a): ?Relief
    {
        $best = null;

        foreach ($a->taxpayer()->reliefs as $relief) {
            if ($relief === Relief::HighMountainResident && $a->is(IncomeKind::Salary) && $a->payerIs(Status::BudgetOrganization)) {
                continue; // 82.2.გ excludes budget-organization and state clinic salaries
            }

            if ($best === null || $relief->annualAllowance()->greaterThan($best->annualAllowance())) {
                $best = $relief;
            }
        }

        return $best;
    }
}
