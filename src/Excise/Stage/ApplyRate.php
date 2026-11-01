<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Stage\FirstMatch;
use GeorgianTax\Excise\Rate\RateRule;

/**
 * Art. 188, 188¹ — the first rate rule that knows the goods: cars, motorcycles, tobacco, alcohol, calls, then the 188.1 table.
 */
final class ApplyRate extends FirstMatch implements Stage
{
    protected function ask(object $handler, object $subject): ?Outcome
    {
        /** @var RateRule $handler */
        return $handler->levy($subject);
    }

    public function handle(object $subject, Closure $next): Outcome
    {
        return parent::handle($subject, static fn () => throw new \LogicException("No excise rate for these goods; IdentifyOperation should have stopped them."));
    }
}
