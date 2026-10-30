<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Exemption;

use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Income\SupplyCategory;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Citation;
use GeorgianTax\ProfitTax\Outflow;
use GeorgianTax\ProfitTax\OutflowKind;

/**
 * Art. 98³.3 — free supplies that are not taxed:
 *   ა  donations to charities up to 10% of last year's net profit;
 *   ბ  gifts already taxed at source under Art. 154 (a gift to an individual — income tax withholds);
 *   გ  free hotel stays of up to 60 days for room owners;
 *   დ  gifts to the state, a municipality or a public-law entity;
 *   ვ  free guarantees and servitudes;
 *   ზ  gifts to another company that pays profit tax under 97.1;
 *   თ  food donated to a charity or food bank.
 */
final class FreeSupplyExceptions implements Exemption
{
    public function exempts(Outflow $o): ?Outcome
    {
        if ($o->kind !== OutflowKind::FreeSupply || !$o->event instanceof Gift) {
            return null;
        }

        $to = $o->event->recipient;
        $what = $o->event->what;

        return match (true) {
            $to->kind === PartyKind::State => $o->notTaxable(new Citation('98³.3.დ'), 'Gifts to the state are not taxed.'),
            $what === SupplyCategory::FoodDonation => $o->notTaxable(new Citation('98³.3.თ'), 'Food donated to a charity or food bank is not taxed.'),
            $what === SupplyCategory::FreeHotelStay => $o->notTaxable(new Citation('98³.3.გ'), 'Free hotel stays for owners are not taxed.'),
            $what === SupplyCategory::FreeServitude => $o->notTaxable(new Citation('98³.3.ვ'), 'Free guarantees and servitudes are not taxed.'),
            $to->kind === PartyKind::Individual => $o->notTaxable(new Citation('98³.3.ბ'), 'A gift to an individual is taxed at source as income (154.1.მ), not as profit.'),
            $to->kind === PartyKind::Company && $to->isResident() => $o->notTaxable(new Citation('98³.3.ზ'), 'A gift to another profit-taxpaying company is not taxed.'),
            $to->is(Status::CharityOrganization) => $this->charity($o),
            default => null,
        };
    }

    /** Up to 10% of the donor's net profit of the previous year, counted across the year. */
    private function charity(Outflow $o): ?Outcome
    {
        $netProfit = $o->books->netProfit($o->company, $o->year() - 1);

        if ($netProfit === null) {
            return $o->unresolved(new Citation('98³.3.ა'), "Charity donations are tax-free up to 10% of last year's net profit, which the books don't give.");
        }

        $limit = $netProfit->scale(0.10);
        $soFar = $o->ledger->spent($o->company, $o->year(), 'charity')->plus($o->amount);

        return $soFar->atMost($limit)
            ? $o->notTaxable(new Citation('98³.3.ა'), "Within this year's charity allowance of $limit (10% of last year's net profit).")
            : null; // over the limit: taxed in full by the standard rule
    }
}
