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
 * (allowance, allowance_disability, allowance_peacekeeper, allowance_high_mountain,
 * allowance_high_mountain_parent), so an amending law changes them without a code change.
 *
 * 82.2.ა.ვ reaches only salary from a budget organization for work in a high-mountain settlement
 * (Pay::$inHighMountainSettlement), and counts only that salary towards its 3,000 GEL: what the
 * same budget organization paid the person this year (two budget employers in one year are for
 * the caller). With three or more children the salary is sheltered up to 82.2.ა's 3,000; with one
 * or two the tax on it, up to its own 3,000, is halved (the rate 82.2.ა.ვ) — the base is cut by that
 * share, which on the flat 20% of salary is the same. For 82.3 the halved relief is worth half its
 * allowance.
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

        $used = $relief->onlyBudgetSalaryInHighMountains()
            ? $a->ledger->receivedFrom($a->taxpayer(), $a->payer(), $a->year(), IncomeKind::Salary)
            : $a->ledger->taxableIncome($a->taxpayer(), $a->year());
        $remaining = $allowance->minus($used)->clampedToZero();

        if ($remaining->isZero()) {
            return $outcome;
        }

        $sheltered = Money::min($remaining, $outcome->base);

        if (($rate = $this->reduction($a, $relief)) !== null) {
            return $outcome->withBase(
                $outcome->base->minus($sheltered->percent($rate)),
                "Art. {$relief->article()}: the tax on $sheltered (of an annual $allowance) cut by $rate%.",
            );
        }

        return $outcome->withBase(
            $outcome->base->minus($sheltered),
            "Art. {$relief->article()}: $sheltered sheltered by the annual allowance of $allowance.",
        );
    }

    /** The share of the tax a relief takes off what it shelters, when not all of it. */
    private function reduction(Assessment $a, Relief $relief): ?float
    {
        $article = $relief->reductionRate();

        return $article === null ? null : $a->rates->rate($article, $a->event->date())->percent;
    }

    /** The relief with the highest allowance in force on the event's day, and that allowance. @return array{Relief, Money}|null */
    private function highestRelief(Assessment $a): ?array
    {
        $best = null;

        foreach ($a->taxpayer()->reliefs as $relief) {
            if ($relief === Relief::HighMountainResident && $a->is(IncomeKind::Salary) && $a->payerIs(Status::BudgetOrganization)) {
                continue; // 82.2.გ excludes budget-organization and state clinic salaries
            }

            if ($relief->onlyBudgetSalaryInHighMountains() && !($a->is(IncomeKind::Salary)
                && $a->payerIs(Status::BudgetOrganization) && ($a->payment()?->inHighMountainSettlement ?? false))) {
                continue; // 82.2.ა.ვ reaches only that salary
            }

            $allowance = $a->threshold($relief->thresholdKey());
            $worth = ($rate = $this->reduction($a, $relief)) === null ? $allowance : $allowance->percent($rate);

            if ($best === null || $worth->greaterThan($best[2])) {
                $best = [$relief, $allowance, $worth];
            }
        }

        return $best === null ? null : [$best[0], $best[1]];
    }
}
