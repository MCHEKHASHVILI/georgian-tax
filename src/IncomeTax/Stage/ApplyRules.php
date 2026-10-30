<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;
use LogicException;

/**
 * The taxing rules, most specific first. The list ends with catch-all rules, so something
 * always answers; reaching $next means the list was wired wrongly.
 */
final readonly class ApplyRules implements Stage
{
    /** @param list<Rule> $rules */
    public function __construct(private array $rules) {}

    public function __invoke(Assessment $a, Closure $next): Outcome
    {
        foreach ($this->rules as $rule) {
            $outcome = $rule->assess($a);

            if ($outcome !== null) {
                return $outcome;
            }
        }

        throw new LogicException('No income tax rule matched. The rule list must end with the catch-all rules.');
    }
}
