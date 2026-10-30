<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Exemption;

use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Inherit;
use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Money;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\Law\Citation;

/**
 *   82.1.ზ  gifts and inheritances from I- and II-order heirs;
 *   82.1.ი  from III- and IV-order heirs, up to 150,000 GEL a year;
 *   82.1.თ  any other gifts up to 1,000 GEL a year — never gifts from an employer.
 */
final class GiftsAndInheritance implements Exemption
{
    public function exempts(Assessment $a): ?Exempt
    {
        $event = $a->event;

        if (!$event instanceof Gift && !$event instanceof Inherit) {
            return null;
        }

        if (in_array($event->relation, [HeirOrder::First, HeirOrder::Second], true)) {
            return $a->exempt(new Citation('82.1.ზ'), 'Gifts and inheritances from I- and II-order heirs are exempt.');
        }

        if (in_array($event->relation, [HeirOrder::Third, HeirOrder::Fourth], true)) {
            $soFar = $a->ledger->receivedFromHeirs($a->taxpayer(), $a->year(), HeirOrder::Third, HeirOrder::Fourth);

            return $soFar->plus($a->income())->atMost(Money::gel(150_000))
                ? $a->exempt(new Citation('82.1.ი'), 'Up to 150,000 GEL a year from III- and IV-order heirs is exempt.')
                : null;
        }

        if ($event instanceof Gift && !$event->fromEmployer) {
            $soFar = $a->ledger->received($a->taxpayer(), $a->year(), IncomeKind::Gift);

            return $soFar->plus($a->income())->atMost(Money::gel(1000))
                ? $a->exempt(new Citation('82.1.თ'), 'Gifts up to 1,000 GEL a year are exempt.')
                : null;
        }

        return null;
    }
}
