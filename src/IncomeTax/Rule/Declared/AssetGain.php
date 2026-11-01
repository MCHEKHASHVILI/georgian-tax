<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Declared;

use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Party\PartyKind;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * A resident individual's gain on a sale not exempted by 82.1.ვ:
 *   81.3  dwelling with land, or a car — 5%;
 *   81.1  any other asset — 20%.
 * Outside a business, declared by the 15th of the next month (153.12). An entrepreneur's business
 * asset is ordinary business income (102.1.ბ) and goes in the annual declaration.
 */
#[Article('81.3, 153.12', 'Gain on selling an asset')]
final class AssetGain implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->event instanceof Sell) {
            return null;
        }

        $asset = $a->event->asset;
        $dwellingOrCar = in_array($asset->type, [AssetType::Dwelling, AssetType::Car], true);
        $businessSale = $a->taxpayer()->kind === PartyKind::IndividualEntrepreneur && $asset->countsAsBusinessAssetOn($a->event->on);

        if ($dwellingOrCar) {
            return $a->declared(new Citation('81.3', 'Gain on a dwelling or car'), '81.3',
                $businessSale ? Collection::AnnualDeclaration : Collection::MonthlyDeclaration);
        }

        return $businessSale
            ? $a->declared(new Citation('102.1.ბ', 'Gain on a business asset'), '81.1', Collection::AnnualDeclaration)
            : $a->declared(new Citation('153.12', 'Gain on a non-business asset'), '81.1', Collection::MonthlyDeclaration);
    }
}
