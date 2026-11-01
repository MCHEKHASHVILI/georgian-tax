<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Stage;

use Closure;
use GeorgianTax\Engine\Stage\FirstMatch;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Rule\Rule;

/**
 * The taxing rules, most specific first. The list ends with catch-all rules, so something
 * always answers; reaching $next means the list was wired wrongly.
 */
final class ApplyRules extends FirstMatch implements Stage
{
    protected function ask(object $handler, object $subject): ?Outcome
    {
        /** @var Rule $handler */
        return $handler->assess($subject);
    }

    public function handle(object $subject, Closure $next): Outcome
    {
        return parent::handle($subject, static fn () => throw new \LogicException('No income tax rule matched. The rule list must end with the catch-all rules.'));
    }
}
