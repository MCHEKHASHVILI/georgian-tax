<?php

declare(strict_types=1);

namespace GeorgianTax\Excise\Stage;

use Closure;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Income\SupplyCategory;
use GeorgianTax\Domain\Money;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Excise\Duty;
use GeorgianTax\Excise\Operation;
use GeorgianTax\Law\Citation;

/**
 * Art. 194. Exempt with credit (194.6–7): exports, duty-free supplies, property seized for the
 * budget, treaty exemptions. Exempt without credit (194.5): the import reliefs — passenger and
 * postal allowances, fuel in the tank, returning residents, diplomats and others — and electric
 * left-hand-drive cars and electric motorcycles (194.5.ლ).
 */
final class ApplyExemptions implements Stage
{
    public function __invoke(Duty $d, Closure $next): Outcome
    {
        $c = $d->commodity;
        $event = $d->event;

        if ($d->operation === Operation::Export) {
            return $d->exemptWithCredit(new Citation('194.6.ა'), 'Exported excise goods — no excise, credit kept.');
        }

        if ($event instanceof Pay) {
            $withCredit = match ($event->supply) {
                SupplyCategory::DutyFree => '194.6.ბ',
                SupplyCategory::PropertySeizedForBudget => '194.6.გ',
                SupplyCategory::UnderTreaty => '194.7',
                default => null,
            };

            if ($withCredit !== null) {
                return $d->exemptWithCredit(new Citation($withCredit), 'Exempt with credit.');
            }
        }

        if (($c->isPassengerCar() && $c->electric && !$c->rightHandDrive) || ($c->isMotorcycle() && $c->electric)) {
            return $d->exempt(new Citation('194.5.ლ'), 'Electric left-hand-drive cars and electric motorcycles are exempt.');
        }

        if ($event instanceof Import && ($relief = $event->relief) !== null && ($article = $relief->exciseArticle()) !== null) {
            $limit = $relief->limitGel();

            if ($limit === null || $event->customsValue->atMost(Money::gel($limit))) {
                return $d->exempt(new Citation($article), "Import relief: {$relief->name}.");
            }
        }

        return $next($d);
    }
}
