<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Stage;

use Closure;
use GeorgianTax\Domain\Income\IncomeKind;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Regime;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Art. 83–95⁶. A special regime replaces the general rules for business income only;
 * salary and other income still go through the general rules (90.3, 95⁵.2).
 */
final class ApplySpecialRegime implements Stage
{
    public function handle(Assessment $a, Closure $next): Outcome
    {
        if (!$a->kind()->isBusinessIncome()) {
            return $next($a);
        }

        return match ($a->taxpayer()->regime) {
            Regime::MicroBusiness => $a->exempt(new Citation('86', 'Micro business'), 'A micro business pays no income tax on its business income.'),
            Regime::SmallBusiness => $this->smallBusiness($a),
            Regime::FixedTax => $a->payment()?->fromFixedTaxActivity
                ? $a->declared(new Citation('95³', 'Fixed tax'), '95³.1.ბ', Collection::MonthlyDeclaration, [
                    'Where the Government set a fixed amount per taxable object (95³.1.ა), that amount applies instead.',
                ])
                : $next($a),
            Regime::General => $next($a),
        };
    }

    /** 1% of turnover; 3% from the month turnover crosses 500,000 GEL (700,000 for wine or agro-tourism). */
    private function smallBusiness(Assessment $a): Outcome
    {
        $threshold = Money::GEL($a->taxpayer()->is(Status::WineOrAgroTourism) ? 700_000 : 500_000);
        $businessKinds = array_filter(IncomeKind::cases(), fn (IncomeKind $k) => $k->isBusinessIncome());
        $turnover = $a->ledger->received($a->taxpayer(), $a->year(), ...$businessKinds)->plus($a->income());

        return $turnover->greaterThan($threshold)
            ? $a->declared(new Citation('90.2', 'Small business above the threshold'), '90.2', Collection::MonthlyDeclaration, [
                "Turnover this year reached $turnover; 3% applies from the start of the month the threshold was crossed.",
            ])
            : $a->declared(new Citation('90.1', 'Small business'), '90.1', Collection::MonthlyDeclaration);
    }
}
