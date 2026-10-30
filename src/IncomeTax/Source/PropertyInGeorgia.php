<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Source;

use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\Law\Article;

/**
 * Selling or renting out property located or used in Georgia (104.1.კ–ნ), and services directly
 * linked to such property (104.1.გ.ბ–გ.გ). Gifts and inheritances of property in Georgia follow the property.
 */
#[Article('104.1.გ.ბ, კ–ნ', 'Property in Georgia')]
final class PropertyInGeorgia implements SourceRule
{
    public function decide(Assessment $a): ?bool
    {
        return $a->event->place()->tiedToPropertyInGeorgia ? true : null;
    }
}
