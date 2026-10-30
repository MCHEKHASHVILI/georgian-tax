<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Vat\Rule\Rule;
use GeorgianTax\Vat\Supply;
use LogicException;

/** Who pays, and when (Art. 161, 165, 165², 168). The list ends with the registration threshold, which always answers. */
final readonly class ApplyLiability implements Stage
{
    /** @param list<Rule> $rules */
    public function __construct(private array $rules) {}

    public function __invoke(Supply $s, Closure $next): Outcome
    {
        foreach ($this->rules as $rule) {
            $outcome = $rule->assess($s);

            if ($outcome !== null) {
                return $outcome;
            }
        }

        throw new LogicException('No VAT liability rule matched. The list must end with RegistrationThreshold.');
    }
}
