<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax\Stage;

use Closure;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Stage\FirstMatch;
use GeorgianTax\ProfitTax\Rule\Rule;

/**
 * Art. 98, 98⁴, 23 — representation over the limit, international company, then the 15% standard rate, which always answers.
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
        return parent::handle($subject, static fn () => throw new \LogicException("StandardRate always answers; the rule list is wired wrongly."));
    }
}
