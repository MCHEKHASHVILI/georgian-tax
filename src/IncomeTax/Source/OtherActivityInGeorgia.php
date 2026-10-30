<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Source;

use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\Law\Article;

/**
 * Catch-all: other income from activity in Georgia. Where nothing above decided, income paid by a
 * resident or a Georgian PE is treated as Georgian. This is an engine default, not a rule of the Code —
 * 104.2 only says the place of payment is irrelevant. Tighten it if your cases need it.
 */
#[Article('104.1.რ', 'Other income from activity in Georgia')]
final class OtherActivityInGeorgia implements SourceRule
{
    public function decide(Assessment $a): ?bool
    {
        if ($a->payment() === null) {
            return false; // sales, gifts and inheritances follow the property (PropertyInGeorgia)
        }

        $payer = $a->payer();

        return $payer !== null && ($payer->isResident() || $payer->hasGeorgianPE);
    }
}
