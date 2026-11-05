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
 * of whatever they decided while the allowance lasts. The amounts are income tax thresholds
 * (allowance, allowance_disability, allowance_peacekeeper, allowance_high_mountain), so an
 * amending law changes them without a code change.
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

        [$relief, $allowance] = $this->highestRelief($a) ?? [null, null];

        if ($relief === null) {
            return $outcome;
        }

        $remaining = $allowance->minus($a->ledger->taxableIncome($a->taxpayer(), $a->year()))->clampedToZero();

        if ($remaining->isZero()) {
            return $outcome;
        }

        $sheltered = Money::min($remaining, $outcome->base);

        return $outcome->withBase(
            $outcome->base->minus($sheltered),
            "Art. {$relief->article()}: $sheltered sheltered by the annual allowance of $allowance.",
        );
    }

    /** The relief with the highest allowance in force on the event's day, and that allowance. @return array{Relief, Money}|null */
    private function highestRelief(Assessment $a): ?array
    {
        $best = null;

        foreach ($a->taxpayer()->reliefs as $relief) {
            if ($relief === Relief::HighMountainResident && $a->is(IncomeKind::Salary) && $a->payerIs(Status::BudgetOrganization)) {
                continue; // 82.2.გ excludes budget-organization and state clinic salaries
            }

            $allowance = $a->threshold($relief->thresholdKey());

            if ($best === null || $allowance->greaterThan($best[1])) {
                $best = [$relief, $allowance];
            }
        }

        return $best;
    }
}
