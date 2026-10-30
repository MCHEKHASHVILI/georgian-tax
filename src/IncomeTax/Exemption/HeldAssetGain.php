<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Exemption;

use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\Law\Citation;

/**
 * Art. 82.1.ვ — gains an individual makes on long-held property:
 *   ვ.ა  dwelling with its land, held more than 2 years;
 *   ვ.ბ  car, held more than 6 months from registration;
 *   ვ.გ  any other asset held more than 2 years and not used in economic activity
 *        (holding securities just for dividends or interest is not economic activity).
 */
final class HeldAssetGain implements Exemption
{
    public function exempts(Assessment $a): ?Exempt
    {
        $sale = $a->event;

        if (!$sale instanceof Sell || !$a->taxpayer()->isIndividual()) {
            return null;
        }

        $asset = $sale->asset;

        return match (true) {
            $asset->type === AssetType::Dwelling && $asset->heldLongerThan('2 years', $sale->on)
                => $a->exempt(new Citation('82.1.ვ.ა'), 'Gain on a dwelling held more than 2 years is exempt.'),

            $asset->type === AssetType::Car && $asset->heldLongerThan('6 months', $sale->on)
                => $a->exempt(new Citation('82.1.ვ.ბ'), 'Gain on a car held more than 6 months is exempt.'),

            !in_array($asset->type, [AssetType::Dwelling, AssetType::Car], true)
                && $asset->heldLongerThan('2 years', $sale->on)
                && !$asset->countsAsBusinessAssetOn($sale->on)
                => $a->exempt(new Citation('82.1.ვ.გ'), 'Gain on an asset held more than 2 years outside economic activity is exempt.'),

            default => null,
        };
    }
}
