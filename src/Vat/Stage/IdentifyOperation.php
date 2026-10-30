<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Stage;

use Closure;
use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\SelfSupply;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;
use GeorgianTax\Vat\Supply;

/**
 * Is this a taxable operation at all (Art. 159)? A supply of goods or services by a taxable person
 * for consideration, an import, or one of the deemed supplies of Art. 160.3 and 160¹.2.
 */
#[Article('159', 'Taxable operations')]
final class IdentifyOperation implements Stage
{
    public function __invoke(Supply $s, Closure $next): Outcome
    {
        $event = $s->event;

        return match (true) {
            $event instanceof Import => $next($s),
            $event instanceof SelfSupply => $this->selfSupply($s, $event, $next),
            $event instanceof Gift => $this->gift($s, $event, $next),
            $event instanceof Sell => $this->sale($s, $event, $next),
            $event instanceof Pay => $this->payment($s, $event, $next),
            default => $s->notTaxable($this, 'The event is not a supply of goods or services, nor an import.'),
        };
    }

    private function payment(Supply $s, Pay $pay, Closure $next): Outcome
    {
        if (!$pay->for->isSupply()) {
            return $s->notTaxable($this, "{$pay->for->name} is not consideration for a supply (employment and transfers are not economic activity, Art. 9.2, 158.4).");
        }

        if ($s->supplier()->kind === PartyKind::State) {
            return $s->notTaxable(new Citation('158.4.ბ'), 'A state body acting as an authority is not a taxable person.');
        }

        return $next($s);
    }

    /** 158.2.ბ–158.3 — one-off sales are not economic activity, except a non-residential building. */
    private function sale(Supply $s, Sell $sale, Closure $next): Outcome
    {
        $seller = $s->supplier();
        $alwaysEconomic = $sale->asset->type === AssetType::RealEstate;

        if ($alwaysEconomic) {
            return $next($s);
        }

        $privateSale = $seller->kind === PartyKind::Individual
            || ($seller->kind === PartyKind::IndividualEntrepreneur && !$sale->asset->countsAsBusinessAssetOn($sale->on));

        return $privateSale
            ? $s->notTaxable(new Citation('158.2.ბ'), "An individual's one-off sale of personal property is not economic activity.")
            : $next($s);
    }

    /** 160.3.ა — a free supply is taxed only if input VAT was credited; samples and gifts ≤ 50 GEL are not (157.ი). */
    private function gift(Supply $s, Gift $gift, Closure $next): Outcome
    {
        if (!$s->supplier()->is(Status::VatPayer)) {
            return $s->notTaxable(new Citation('160.3.ა'), 'A free supply by someone who credited no input VAT is not a supply for consideration.');
        }

        if ($gift->recipient->isIndividual() && $gift->value->atMost(Money::gel(50))) {
            return $s->notTaxable(new Citation('157.ი'), 'A gift worth 50 GEL or less a year to one person is not a supply.');
        }

        return $next($s);
    }

    private function selfSupply(Supply $s, SelfSupply $self, Closure $next): Outcome
    {
        if ($self->reason->needsCreditedInput() && !$self->inputVatCredited) {
            return $s->notTaxable(new Citation($self->reason->value), 'No input VAT was credited, so there is no deemed supply.');
        }

        return $next($s);
    }
}
