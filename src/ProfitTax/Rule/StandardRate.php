<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Rule;

use GeorgianTax\Domain\Event\Expense;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;
use GeorgianTax\ProfitTax\Outflow;
use GeorgianTax\ProfitTax\OutflowKind;

/**
 * 15% of the outflow grossed up by 0.85 (98.1, 97.10), declared and paid by the 15th of next month
 * (153.10). Cites the article that makes this particular outflow taxable.
 * Loans and cash collateral by banks and licensed lenders are carved out of 98².3 (ზ, ზ¹, დ).
 * A non-profit organization is taxed only on non-purpose costs and free supplies (97.2).
 */
#[Article('98.1', 'Profit tax rate')]
final class StandardRate implements Rule
{
    public function assess(Outflow $o): ?Outcome
    {
        $event = $o->event;

        if ($event instanceof Expense && $event->nature->excludedForLenders() && $o->company->is(Status::LicensedFinancialInstitution)) {
            return $o->notTaxable(new Citation($event->nature->value), 'A licensed lender making loans is outside this rule.');
        }

        if ($o->isOrganization() && in_array($o->kind, [OutflowKind::Distribution, OutflowKind::DeemedDistribution], true)) {
            return $o->notTaxable(new Citation('97.2'), 'A non-profit organization is taxed only on non-purpose costs and free supplies.');
        }

        $basis = match ($o->kind) {
            OutflowKind::NonBusinessExpense => new Citation($event->nature->value, 'Expense not related to economic activity'),
            OutflowKind::Distribution => new Citation('98¹.1', 'Distributed profit'),
            OutflowKind::DeemedDistribution => new Citation('98¹.4', 'Deemed distribution — non-market deal'),
            OutflowKind::FreeSupply => new Citation('98³', 'Free supply'),
            OutflowKind::Representation => new Citation('98⁴'),
        };

        return $o->taxed($basis, '98.1');
    }
}
