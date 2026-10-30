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
 * A registered VAT payer charges 18% and declares by the 15th of next month (165².1, 168.1).
 * It issues a tax invoice to business customers, and to consumers on request (180).
 * Under the margin scheme the base is the margin ÷ 1.18, and nobody credits it (161²).
 */
#[Article('168.1', 'Registered VAT payer')]
final class RegisteredSupplier implements Rule
{
    public function assess(Supply $s): ?Outcome
    {
        if (!$s->supplier()->is(Status::VatPayer)) {
            return null;
        }

        if ($s->usesMarginScheme()) {
            return $s->charged(new Citation('161²', 'Margin scheme'), Collection::MonthlyDeclaration, [
                'Taxed on the margin; neither the dealer nor the buyer credits this VAT (161².5–6).',
            ]);
        }

        return $s->charged($this, Collection::MonthlyDeclaration);
    }
}
