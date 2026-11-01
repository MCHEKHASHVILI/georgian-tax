<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Place;

use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\Vat\Supply;

/** General rule for business customers: where the customer is established, or its fixed establishment served. */
#[Article('162¹.3.ა', 'Services to a business')]
final class BusinessCustomer implements PlaceRule
{
    public function inGeorgia(Supply $s): ?bool
    {
        if (!$s->customerIsBusiness()) {
            return null;
        }

        $customer = $s->customer();

        return $customer->country === 'GE' || $customer->hasGeorgianPE;
    }
}
