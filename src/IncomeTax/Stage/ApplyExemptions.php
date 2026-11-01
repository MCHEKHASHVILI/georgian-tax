<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Stage;

use GeorgianTax\Engine\Stage\FirstMatch;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Exemption\Exemption;

/** Art. 82.1, 103.1 and the exemptions scattered through Art. 23, 94, 130–132. First match wins. */
final class ApplyExemptions extends FirstMatch implements Stage
{
    protected function ask(object $handler, object $subject): ?Outcome
    {
        /** @var Exemption $handler */
        return $handler->exempts($subject);
    }
}
