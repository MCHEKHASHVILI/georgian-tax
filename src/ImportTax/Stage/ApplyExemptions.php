<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Stage;

use Closure;
use DateTimeImmutable;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Goods\ImportRelief as R;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\ImportTax\Clearance;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Art. 199 exemptions:
 *   by goods — electric cars (ჟ), salt, minerals, abrasives and denatured alcohol (პ)
 *   by relief — humanitarian and grant imports, traveller and postal allowances, returning
 *               residents, raw tobacco until 2028, customs procedures, free-zone goods, CIS origin…
 * Value limits apply to customs value and are import tax thresholds (ImportRelief::thresholdKey()),
 * so an amending law changes them without a code change; the HS chapters each traveller relief
 * covers are checked here too. Quantity and frequency limits (200 cigarettes, once in 30 days),
 * and that a returning diplomat's goods are the listed ones (199.დ.დ), are for the caller.
 *
 * 199.დ.ვ relieves a parcel's goods of chapters 28–97 up to 300 GEL, "and also" the tobacco
 * products it lists and "4 litres of all kinds of alcoholic drink" — a clause with no value
 * limit, so those pass whatever their customs value (postalTobaccoOrAlcohol()).
 */
#[Article('199', 'Import tax exemptions')]
final class ApplyExemptions implements Stage
{
    public function handle(Clearance $c, Closure $next): Outcome
    {
        $goods = $c->commodity;
        $import = $c->import;

        if ($goods->isPassengerCar() && $goods->electric) {
            return $c->exempt(new Citation('199.ჟ'), 'Electric passenger cars are exempt from import tax.');
        }

        if ($c->table->exemptByCode($goods)) {
            return $c->exempt(new Citation('199.პ'), "HS {$goods->hsCode} is exempt from import tax.");
        }

        $relief = $import->relief;
        $article = $relief?->importTaxArticle();

        if ($article === null) {
            return $next($c);
        }

        $key = $relief->thresholdKey();
        $until = $relief->validUntil();

        if ($relief === R::PostalSmall && self::postalTobaccoOrAlcohol($goods)) {
            return $c->exempt(
                new Citation($article),
                'Import relief: PostalSmall — tobacco or alcohol by post, with no value limit; the quantities (200 cigarettes, 4 litres…) are for the caller.',
            );
        }

        $applies = ($key === null || $import->customsValue->lessThanOrEqual($c->threshold($key)))
            && ($until === null || $import->on < new DateTimeImmutable($until))
            && self::coversChapter($relief, $goods);

        return $applies ? $c->exempt(new Citation($article), "Import relief: {$relief->name}.") : $next($c);
    }

    /**
     * 199.დ.ვ's second clause, with no value limit: cigarettes, cigars and cigarillos (2402),
     * other tobacco products but raw tobacco (2403; 2401 is raw tobacco), 2404 11 000 00 and its
     * capsules, 2404 12 000 00 and 2404 19 900 00, and alcoholic drinks of every kind — beer,
     * wine, vermouth, other fermented drinks and spirits (2203–2206, 2208). Undenatured ethyl
     * alcohol (2207) is not a drink: 199.დ.გ¹ names it beside the drinks. The rest of 2404 is not
     * in the list.
     */
    private static function postalTobaccoOrAlcohol(Commodity $goods): bool
    {
        foreach (['2402', '2403', '240411', '240412', '24041990', '2203', '2204', '2205', '2206', '2208'] as $prefix) {
            if ($goods->startsWith($prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Which HS chapters each traveller and postal relief covers (199.დ). */
    private static function coversChapter(R $relief, Commodity $goods): bool
    {
        $chapter = (int) substr($goods->digits(), 0, 2);
        $heading = (int) substr($goods->digits(), 0, 4);

        return match ($relief) {
            R::TravellerFood => in_array($chapter, [2, 4, 6, 7, 8, 9, 10, 11, 12, 15, 16, 17, 18, 19, 20, 21], true)
                || ($heading >= 302 && $heading <= 307) || ($heading >= 2201 && $heading <= 2202),
            R::PersonalGoods, R::PostalSmall, R::AirTravellerGoods => $chapter >= 28 && $chapter <= 97,
            R::ReturningResident => $chapter >= 28 && $chapter <= 97 && $chapter !== 87,
            default => true,
        };
    }
}
