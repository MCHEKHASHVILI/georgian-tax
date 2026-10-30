<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Place;

use GeorgianTax\Domain\Income\SupplyCategory as C;
use GeorgianTax\Law\Article;
use GeorgianTax\Vat\Supply;

/**
 * For consumers, these services are supplied where the *consumer* lives: telecom, broadcasting,
 * electronic services, IP rights, advertising, consulting and similar professional services,
 * finance and insurance, staffing, and renting out movables other than vehicles.
 */
#[Article('162¹.14', 'Services to consumers taxed where the consumer is')]
final class ConsumerLocation implements PlaceRule
{
    private const SERVICES = [
        C::Telecom, C::Broadcasting, C::ElectronicService, C::IntellectualProperty, C::Advertising,
        C::Consulting, C::Financial, C::Staffing, C::MovableRental,
    ];

    public function inGeorgia(Supply $s): ?bool
    {
        if ($s->customerIsBusiness() || !$s->is(...self::SERVICES) || $s->customer() === null) {
            return null;
        }

        return $s->customer()->country === 'GE';
    }
}
