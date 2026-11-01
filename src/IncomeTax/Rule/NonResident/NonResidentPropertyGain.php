<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\NonResident;

use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Sell;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use Mchekhashvili\TaxLegislation\Law\Article;

/**
 * A non-resident individual selling property with a Georgian source is not withheld under 134 (80.3);
 * they declare the gain net of related deductions for the year (80.4–80.5).
 */
#[Article('80.4', "Non-resident individual's gain on property")]
final class NonResidentPropertyGain implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->event instanceof Sell || $a->taxpayer()->isResident() || !$a->taxpayer()->isIndividual()) {
            return null;
        }

        $dwellingOrCar = in_array($a->event->asset->type, [AssetType::Dwelling, AssetType::Car], true);

        return $a->declared($this, $dwellingOrCar ? '81.3' : '81.1', Collection::AnnualDeclaration);
    }
}
