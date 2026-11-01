<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Stage\FirstMatch;
use GeorgianTax\ImportTax\Rule\Rule;

/**
 * Art. 197 — cars per cm³ and age, alcohol in euro, then the 12% and 5% lists, which answer 0% for anything unlisted.
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
        return parent::handle($subject, static fn () => throw new \LogicException("ListedRate answers every import; the rule list is wired wrongly."));
    }
}
