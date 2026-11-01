<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Stage;

use Closure;
use Mchekhashvili\TaxLegislation\Engine\Stage\FirstMatch;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\Vat\Rule\Rule;
use GeorgianTax\Vat\Supply;

/** Who pays, and when (Art. 161, 165, 165², 168). The list ends with the registration threshold, which always answers. */
final class ApplyLiability extends FirstMatch implements Stage
{
    protected function ask(object $handler, object $subject): ?Outcome
    {
        /** @var Rule $handler */
        return $handler->assess($subject);
    }

    public function handle(object $subject, Closure $next): Outcome
    {
        return parent::handle($subject, static fn () => throw new \LogicException('No VAT liability rule matched. The list must end with RegistrationThreshold.'));
    }
}
