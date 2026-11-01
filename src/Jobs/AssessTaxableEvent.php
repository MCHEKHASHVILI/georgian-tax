<?php

declare(strict_types=1);

namespace GeorgianTax\Jobs;

use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\TaxEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Assesses an event on the queue: GeorgianTax::queue($event). Listen for TaxableEventAssessed
 * to act on the result.
 *
 * Events for the same party should be assessed in order, because each one reads the totals the
 * earlier ones left in the ledger. Use a queue with a single worker, or chain the jobs.
 */
final class AssessTaxableEvent implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public readonly TaxableEvent $event)
    {
        $this->onConnection(config('georgian-tax.queue.connection'));
        $this->onQueue(config('georgian-tax.queue.queue'));
    }

    public function handle(TaxEngine $engine): void
    {
        $engine->process($this->event);
    }
}
