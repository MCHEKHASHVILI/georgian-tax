<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Stage;

use Closure;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Stage\FirstMatch;
use GeorgianTax\ProfitTax\Exemption\Exemption;

/**
 * Art. 98¹.2, 98³.3, 99, 23¹ — not a distribution, an exempt company, or an untaxed free supply.
 */
final class ApplyExemptions extends FirstMatch implements Stage
{
    protected function ask(object $handler, object $subject): ?Outcome
    {
        /** @var Exemption $handler */
        return $handler->exempts($subject);
    }
}
