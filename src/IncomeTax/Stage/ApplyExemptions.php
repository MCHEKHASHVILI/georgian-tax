<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Exemption\Exemption;

/** Art. 82.1, 103.1 and the exemptions scattered through Art. 23, 94, 130–132. First match wins. */
final readonly class ApplyExemptions implements Stage
{
    /** @param list<Exemption> $exemptions */
    public function __construct(private array $exemptions) {}

    public function __invoke(Assessment $a, Closure $next): Outcome
    {
        foreach ($this->exemptions as $exemption) {
            $exempt = $exemption->exempts($a);

            if ($exempt !== null) {
                return $exempt;
            }
        }

        return $next($a);
    }
}
