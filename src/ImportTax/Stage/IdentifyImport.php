<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Stage;

use Closure;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\ImportTax\Clearance;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;

/**
 * Import tax is paid by whoever moves goods across Georgia's customs border, other than for export
 * (195), on their customs value (196). Every other event is outside it. The rate depends on the HS
 * code, so an import without one cannot be computed.
 */
#[Article('195–196', 'Import tax payer and object')]
final class IdentifyImport implements Stage
{
    public function __invoke(Clearance $c, Closure $next): Outcome
    {
        $event = $c->event;

        if (!$event instanceof Import) {
            return $c->notTaxable($this, 'Only goods imported into Georgia carry import tax.');
        }

        if ($event->commodity === null) {
            return $c->unresolved(new Citation('197'), 'The import tax rate depends on the HS code — describe the goods with a Commodity.');
        }

        return $next($c->of($event, $event->commodity));
    }
}
