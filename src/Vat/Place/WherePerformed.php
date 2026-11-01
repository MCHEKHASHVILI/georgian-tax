<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Place;

use GeorgianTax\Domain\Income\SupplyCategory as C;
use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\Vat\Supply;

/**
 * Services taxed where they physically happen:
 *   162¹.6–7  cultural, educational, sports and entertainment events, and admission to them;
 *   162¹.9    passenger transport (by distance — the engine takes the place given on the event);
 *   162¹.11   restaurant and catering;
 *   162¹.12   short-term vehicle rental, where the vehicle is handed over;
 * and, for consumers only:
 *   162¹.5    an intermediary's service, where the underlying operation happens;
 *   162¹.8    transport-ancillary services and work on movable property;
 *   162¹.10   goods transport.
 */
#[Article('162¹.5–12', 'Services taxed where performed')]
final class WherePerformed implements PlaceRule
{
    private const ALWAYS = [C::EventOrAdmission, C::PassengerTransport, C::Restaurant, C::ShortTermVehicleRental];
    private const CONSUMERS_ONLY = [C::Intermediary, C::TransportAncillary, C::MovablePropertyWork, C::GoodsTransport];

    public function inGeorgia(Supply $s): ?bool
    {
        $applies = $s->is(...self::ALWAYS) || (!$s->customerIsBusiness() && $s->is(...self::CONSUMERS_ONLY));

        return $applies ? $s->event->place()->isGeorgia() : null;
    }
}
