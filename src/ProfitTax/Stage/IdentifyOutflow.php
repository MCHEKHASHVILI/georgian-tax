<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Stage;

use Closure;
use GeorgianTax\Domain\Event\Expense;
use GeorgianTax\Domain\Event\ExpenseNature;
use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\NonMarketDeal;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\SelfSupply;
use GeorgianTax\Domain\Event\SelfSupplyReason;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;
use GeorgianTax\ProfitTax\Outflow;
use GeorgianTax\ProfitTax\OutflowKind;

/**
 * Who pays profit tax (Art. 96) and on what (Art. 97). A resident company or a Georgian PE pays on
 * value leaving it; ordinary income and ordinary business costs create no profit tax at all.
 */
#[Article('96–97', 'Profit taxpayer and object')]
final class IdentifyOutflow implements Stage
{
    public function handle(Outflow $o, Closure $next): Outcome
    {
        $event = $o->event;
        $company = $event instanceof Gift ? $event->donor : $event->payer() ?? $event->recipient();

        if (!$this->isProfitTaxpayer($company)) {
            return $this->receiverNote($o) ?? $o->notTaxable($this, 'No resident company or Georgian PE is paying anything out.');
        }

        if ($company->is(Status::CreditInstitution)) {
            return $o->notTaxable(new Citation('97.12'), 'Banks and lenders pay profit tax on annual net profit at 20% (98.4), not on distributions.');
        }

        return match (true) {
            $event instanceof Pay && $event->for === IncomeKind::Dividend
                => $next($o->as($company, OutflowKind::Distribution, $event->amount)),
            $event instanceof NonMarketDeal => $event->shortfall()->isPositive()
                ? $next($o->as($company, OutflowKind::DeemedDistribution, $event->shortfall()))
                : $o->notTaxable(new Citation('98¹.4'), 'The deal was at or above market for the company; nothing was distributed.'),
            $event instanceof Expense => $next($o->as(
                $company,
                $event->nature === ExpenseNature::Representation ? OutflowKind::Representation : OutflowKind::NonBusinessExpense,
                $event->amount,
            )),
            $event instanceof Gift => $next($o->as($company, OutflowKind::FreeSupply, $event->value)),
            $event instanceof SelfSupply && $event->reason === SelfSupplyReason::Shortage
                => $next($o->as($company, OutflowKind::FreeSupply, $event->value)),
            default => $this->receiverNote($o)
                ?? $o->notTaxable(new Citation('97.1'), 'An ordinary business payment; profit tax arises only when profit leaves the company.'),
        };
    }

    /** Art. 96 — a resident company or organization, or a non-resident's Georgian PE. */
    private function isProfitTaxpayer(?Party $party): bool
    {
        if ($party === null || !in_array($party->kind, [PartyKind::Company, PartyKind::Organization], true)) {
            return false;
        }

        return $party->isResident() || $party->hasGeorgianPE;
    }

    /** A company *receiving* income pays nothing until it distributes (97.1); a foreign one is withheld under 134 (97.4). */
    private function receiverNote(Outflow $o): ?Outcome
    {
        $recipient = $o->event->recipient();

        if (!in_array($recipient->kind, [PartyKind::Company, PartyKind::Organization], true) || $o->event->income()->isZero()) {
            return null;
        }

        return $recipient->isNonResidentWithoutPE()
            ? $o->notTaxable(new Citation('97.4'), "A non-resident's Georgian-source income is withheld at source under Art. 134 — see the income outcome.")
            : $o->notTaxable(new Citation('97.1'), 'Income received is not taxed; profit tax arises when it is distributed.');
    }
}
