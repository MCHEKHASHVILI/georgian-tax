<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Stage;

use Closure;
use DateTimeImmutable;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Goods\ImportRelief as R;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\ImportTax\Clearance;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Art. 199 exemptions:
 *   by goods — electric cars (ჟ), salt, minerals, abrasives and denatured alcohol (პ)
 *   by relief — humanitarian and grant imports, traveller and postal allowances, returning
 *               residents, raw tobacco until 2028, customs procedures, free-zone goods, CIS origin…
 * Value limits apply to customs value; the HS chapters each traveller relief covers are checked
 * here too. Quantity and frequency limits (200 cigarettes, once in 30 days) are for the caller.
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

        $limit = $relief->limitGel();
        $until = $relief->validUntil();

        $applies = ($limit === null || $import->customsValue->lessThanOrEqual(Money::GEL($limit)))
            && ($until === null || $import->on < new DateTimeImmutable($until))
            && self::coversChapter($relief, $goods);

        return $applies ? $c->exempt(new Citation($article), "Import relief: {$relief->name}.") : $next($c);
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
