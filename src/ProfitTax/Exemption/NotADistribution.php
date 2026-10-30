<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Exemption;

use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Citation;
use GeorgianTax\ProfitTax\Outflow;
use GeorgianTax\ProfitTax\OutflowKind;

/**
 * Art. 98¹.2 — payments that look like dividends but aren't distributions:
 *   გ  dividends to a resident commercial company (not an individual enterprise, not a tax-exempt one);
 *   დ  a state-majority company returning assets to the state by reducing capital.
 * And dividends paid out of profit the Code exempts — deemed paid first (98¹.5, 99.3).
 * Returns of capital up to contributions (98¹.2.ა) and payouts in own shares (ბ) should simply
 * not be recorded as Dividend.
 */
final class NotADistribution implements Exemption
{
    public function exempts(Outflow $o): ?Outcome
    {
        if ($o->kind !== OutflowKind::Distribution || !$o->event instanceof Pay) {
            return null;
        }

        $partner = $o->event->payee;
        $source = $o->event->fromExemptProfit;

        return match (true) {
            $partner->kind === PartyKind::Company && $partner->isResident()
                => $o->notTaxable(new Citation('98¹.2.გ'), 'A dividend to a resident company is not a distribution; the receiving company is taxed when it distributes.'),
            $partner->kind === PartyKind::State && $o->company->is(Status::BudgetOrganization)
                => $o->notTaxable(new Citation('98¹.2.დ'), 'A state-majority company returning assets to the state is not a distribution.'),
            $source !== null && ($source->validUntil() === null || $o->event->on < new \DateTimeImmutable($source->validUntil()))
                => $o->exempt(new Citation($source->value), "Paid out of profit exempt under Art. {$source->value}."),
            default => null,
        };
    }
}
