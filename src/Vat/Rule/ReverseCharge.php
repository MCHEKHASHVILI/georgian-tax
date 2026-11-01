<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Rule;

use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\Vat\Supply;

/**
 * A foreign supplier with no Georgian establishment serves a customer established in Georgia:
 * the customer charges itself VAT on what it pays (161.3.ა) and pays it by the 15th (168.4).
 * The customer is the tax agent unless it's a private individual or a free-industrial-zone company
 * (161.2.ა). A registered customer credits the same amount (175.1.დ, 176.1.გ).
 */
#[Article('161.1.ა', 'Reverse charge')]
final class ReverseCharge implements Rule
{
    public function assess(Supply $s): ?Outcome
    {
        $supplier = $s->supplier();
        $customer = $s->customer();

        if (!$s->isService() || $supplier->isResident() || $supplier->hasGeorgianPE || $customer === null) {
            return null;
        }

        $customerIsAgent = $customer->kind !== PartyKind::Individual
            && !$customer->is(Status::FreeIndustrialZone)
            && ($customer->isResident() || $customer->hasGeorgianPE);

        return $customerIsAgent
            ? $s->charged($this, Collection::ReverseCharge, [], $customer)
            : null;
    }
}
