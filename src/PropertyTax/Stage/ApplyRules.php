<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Stage\FirstMatch;
use GeorgianTax\PropertyTax\Rule\Rule;

/**
 * Art. 202, 204 — land per hectare or m², then company or individual property by value.
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
        return parent::handle($subject, static fn () => throw new \LogicException("Every holding is land, a company's or an individual's; the rule list is wired wrongly."));
    }
}
