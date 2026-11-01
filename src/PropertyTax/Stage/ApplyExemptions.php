<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax\Stage;

use Closure;
use DateTimeImmutable;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Domain\Property\PropertyKind as K;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;
use GeorgianTax\PropertyTax\Holding;

/**
 * Art. 206. Decided here from the owner and the item:
 *   ა   an individual's property (not land) when the family earned 100,000 GEL or less last year
 *   ე   an organization's property, other than land and property used in economic activity
 *   ჩ   property in a free industrial zone
 *   ჰ3  yachts and boats (HS 8903)
 *   ჰ4  property of a bankrupt
 *   ჰ6  a high-mountain enterprise's property, for 10 calendar years from the status
 * And any PropertyExemption the caller asserts, within its dates.
 * 206.2: an exemption does not cover land leased out, nor a company's buildings leased out,
 * except a state university's.
 */
#[Article('206', 'Property tax exemptions')]
final class ApplyExemptions implements Stage
{
    private const FAMILY_INCOME_LIMIT = 100_000;

    public function handle(Holding $h, Closure $next): Outcome
    {
        $owner = $h->holding->owner;
        $item = $h->item();
        $kind = $item->kind;
        $year = $h->holding->year;

        $leasingBlocks = $item->leasedOut && ($kind->isLand() || ($kind->isRealEstate() && !$h->isIndividual()));

        if (($e = $item->exemption) !== null) {
            $expired = $e->validUntil() !== null && new DateTimeImmutable("$year-01-01") >= new DateTimeImmutable($e->validUntil());

            if (!$expired && (!$leasingBlocks || $e->survivesLeasing())) {
                return $h->exempt(new Citation($e->value), "Exempt: {$e->name}.");
            }
        }

        if ($leasingBlocks) {
            return $next($h);
        }

        if ($kind === K::Yacht) {
            return $h->exempt(new Citation('206.1.ჰ3'), 'Yachts and boats (HS 8903) are exempt.');
        }

        if ($owner->is(Status::Bankrupt)) {
            return $h->exempt(new Citation('206.1.ჰ4'), 'Property of a person in bankruptcy is exempt.');
        }

        if ($owner->is(Status::FreeIndustrialZone)) {
            return $h->exempt(new Citation('206.1.ჩ'), 'Property in a free industrial zone is exempt.');
        }

        if ($owner->is(Status::HighMountainEnterprise) && ($since = $owner->since(Status::HighMountainEnterprise)) !== null
            && $year < (int) $since->format('Y') + 10) {
            return $h->exempt(new Citation('206.1.ჰ6'), 'High-mountain enterprise: exempt for 10 calendar years.');
        }

        if ($owner->kind === PartyKind::Organization && !$kind->isLand() && !$item->usedInBusiness) {
            return $h->exempt(new Citation('206.1.ე'), 'An organization\'s property not used in economic activity is exempt.');
        }

        if ($h->isIndividual() && !$kind->isLand()) {
            $income = $h->holding->familyIncome;

            if ($income === null) {
                return $h->unresolved(new Citation('206.1.ა'), 'An individual\'s property tax turns on last year\'s family income — give it.');
            }

            if ($income->lessThanOrEqual(Money::GEL(self::FAMILY_INCOME_LIMIT))) {
                return $h->exempt(new Citation('206.1.ა'), 'Family income of 100,000 GEL or less last year.');
            }
        }

        return $next($h);
    }
}
