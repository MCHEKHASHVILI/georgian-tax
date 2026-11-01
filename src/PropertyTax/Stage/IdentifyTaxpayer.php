<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax\Stage;

use Closure;
use GeorgianTax\Domain\Event\OwnProperty;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;
use GeorgianTax\PropertyTax\Holding;

/**
 * Who pays on what (201, 203):
 *   companies and organizations — fixed assets, uninstalled equipment, unfinished construction,
 *     property leased out, and property taken over from a debtor (201.1.ა–ბ)
 *   individuals — real estate, yachts, helicopters, aircraft and cars (201.1.გ.ა); fixed assets
 *     too when used in economic activity (გ.გ)
 *   everyone — land owned, or state land used or held (203)
 * The state pays no property tax on its own property.
 */
#[Article('201, 203', 'Property tax payer and object')]
final class IdentifyTaxpayer implements Stage
{
    public function handle(Holding $h, Closure $next): Outcome
    {
        $event = $h->event;

        if (!$event instanceof OwnProperty) {
            return $h->notTaxable($this, 'Property tax is charged on property held, not on transactions.');
        }

        $h = $h->of($event);
        $kind = $event->item->kind;

        if ($event->owner->kind === PartyKind::State) {
            return $h->notTaxable($this, 'The state does not pay property tax on its own property.');
        }

        if ($h->isIndividual() && !$kind->isLand() && !$kind->isPersonalTaxable() && !$event->item->usedInBusiness) {
            return $h->notTaxable(new Citation('201.1.გ'), 'An individual pays on equipment only when it is used in economic activity.');
        }

        return $next($h);
    }
}
