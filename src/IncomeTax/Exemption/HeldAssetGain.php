<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Exemption;

use DateTimeImmutable;
use GeorgianTax\Domain\Asset\Asset;
use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Period;
use GeorgianTax\Engine\LawTables;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use GeorgianTax\IncomeTax\Assessment;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Art. 82.1.ვ — gains an individual makes on long-held property:
 *   ვ.ა  dwelling with its land, held more than 2 years;
 *   ვ.ბ  car, held more than 6 months from registration;
 *   ვ.გ  any other asset held more than 2 years and not used in economic activity
 *        (holding securities just for dividends or interest is not economic activity), business
 *        use being ignored once 2 years have passed since it ended (ვ.გ's note).
 *
 * The periods are law (`income.periods`, keyed 82.1.ვ.ა, ვ.ბ, ვ.გ and ვ.გ.note), read on the day of
 * the sale, so an amending law changes them without code. A holding period not in force exempts
 * nothing; with the note's period not in force, business use always counts.
 */
final class HeldAssetGain implements Exemption
{
    public function __construct(private readonly LawTables $tables) {}

    public function exempts(Assessment $a): ?Exempt
    {
        $sale = $a->event;

        if (!$sale instanceof Sell || !$a->taxpayer()->isIndividual()) {
            return null;
        }

        $asset = $sale->asset;
        $periods = $this->tables->periods($sale->on);
        $held = static fn (string $key) => ($period = $periods->of(Assessment::TAX, $key)) !== null && $asset->heldLongerThan($period, $sale->on)
            ? $period
            : null;

        return match (true) {
            $asset->type === AssetType::Dwelling => ($p = $held('82.1.ვ.ა')) === null ? null
                : $a->exempt(new Citation('82.1.ვ.ა'), "Gain on a dwelling held more than {$p->describe()} is exempt."),

            $asset->type === AssetType::Car => ($p = $held('82.1.ვ.ბ')) === null ? null
                : $a->exempt(new Citation('82.1.ვ.ბ'), "Gain on a car held more than {$p->describe()} is exempt."),

            default => ($p = $held('82.1.ვ.გ')) === null || $this->usedInBusiness($asset, $sale->on, $periods->of(Assessment::TAX, '82.1.ვ.გ.note'))
                ? null
                : $a->exempt(new Citation('82.1.ვ.გ'), "Gain on an asset held more than {$p->describe()} outside economic activity is exempt."),
        };
    }

    /** ვ.გ's note: business use counts unless the note's period has passed since it ended; with the note repealed, it always counts. */
    private function usedInBusiness(Asset $asset, DateTimeImmutable $on, ?Period $note): bool
    {
        return $note === null ? $asset->usedInBusiness : $asset->countsAsBusinessAssetOn($on, $note);
    }
}
