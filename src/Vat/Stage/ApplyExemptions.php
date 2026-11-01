<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Stage;

use Mchekhashvili\TaxLegislation\Engine\Stage\FirstMatch;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\Vat\Exemption\Exemption;
use GeorgianTax\Vat\Supply;

/** Art. 170–173. First match wins; with-credit exemptions are listed before without-credit ones. */
final class ApplyExemptions extends FirstMatch implements Stage
{
    protected function ask(object $handler, object $subject): ?Outcome
    {
        /** @var Exemption $handler */
        return $handler->exempts($subject);
    }
}
