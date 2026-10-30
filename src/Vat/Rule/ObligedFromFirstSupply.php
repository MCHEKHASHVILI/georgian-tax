<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Rule;

use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;
use GeorgianTax\Vat\Supply;

/**
 * Unregistered suppliers who owe VAT from their very first supply, with no threshold:
 *   165.9  a foreign taxable person with a fixed establishment in Georgia — registers by the end of the period;
 *   165.3  a producer of excise goods — must register before the first supply.
 */
#[Article('165.3, 165.9', 'VAT from the first supply')]
final class ObligedFromFirstSupply implements Rule
{
    public function assess(Supply $s): ?Outcome
    {
        $supplier = $s->supplier();

        return match (true) {
            !$supplier->isResident() && $supplier->hasGeorgianPE => $s->charged(new Citation('165.9'), Collection::MonthlyDeclaration, [
                'A fixed establishment in Georgia owes VAT from its first supply; register by the end of this period.',
            ]),
            $supplier->is(Status::ExciseProducer) => $s->charged(new Citation('165.3'), Collection::MonthlyDeclaration, [
                'An excise goods producer had to register before its first supply.',
            ]),
            default => null,
        };
    }
}
