<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Rule\Declared;

use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Inherit;
use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;

/**
 * A gift or inheritance no exemption released, received from someone who isn't a tax agent.
 * III/IV-order heirs above 150,000 GEL a year pay over two calendar years (153.7);
 * the registry warns the recipient before registering the property (153.6).
 */
#[Article('80.1, 153.6', 'Gift or inheritance received')]
final class GiftOrInheritance implements Rule
{
    public function assess(Assessment $a): ?Outcome
    {
        $event = $a->event;

        if (!$event instanceof Gift && !$event instanceof Inherit) {
            return null;
        }

        if (in_array($event->relation, [HeirOrder::Third, HeirOrder::Fourth], true)) {
            return $a->declared(new Citation('153.7', 'Gift or inheritance from distant heirs above 150,000 GEL'), '81.1', Collection::OverTwoYears);
        }

        return $a->declared($this, '81.1', Collection::AnnualDeclaration);
    }
}
