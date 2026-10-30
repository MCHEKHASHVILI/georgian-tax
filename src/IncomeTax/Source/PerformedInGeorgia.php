<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Source;

use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\Law\Article;

/** A service actually performed in Georgia (104.1.გ.ა), goods supplied on Georgian territory (104.1.ბ). */
#[Article('104.1.ბ, გ.ა', 'Supplied or performed in Georgia')]
final class PerformedInGeorgia implements SourceRule
{
    public function decide(Assessment $a): ?bool
    {
        return $a->event->place()->isGeorgia() ? true : null;
    }
}
