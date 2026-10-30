<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Vat\Exemption\Exemption;
use GeorgianTax\Vat\Supply;

/** Art. 170–173. First match wins; with-credit exemptions are listed before without-credit ones. */
final readonly class ApplyExemptions implements Stage
{
    /** @param list<Exemption> $exemptions */
    public function __construct(private array $exemptions) {}

    public function __invoke(Supply $s, Closure $next): Outcome
    {
        foreach ($this->exemptions as $exemption) {
            $outcome = $exemption->exempts($s);

            if ($outcome !== null) {
                return $outcome;
            }
        }

        return $next($s);
    }
}
