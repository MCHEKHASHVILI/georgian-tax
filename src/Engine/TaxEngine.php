<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Events\TaxableEventAssessed;
use GeorgianTax\Events\VatRegistrationRequired;
use GeorgianTax\Jobs\AssessTaxableEvent;
use GeorgianTax\Ledger\YearToDate;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Runs an event through every tax, records it in the year-to-date ledger, and tells the app.
 *
 *     GeorgianTax::process($event)   assess, record, fire TaxableEventAssessed
 *     GeorgianTax::assess($event)    a quote: assess only — nothing recorded, nothing fired
 *     GeorgianTax::queue($event)     process on the queue
 *     GeorgianTax::only('vat')       an engine with just these taxes
 *
 * Taxes run in the order config('georgian-tax.taxes') lists them; each sees what the earlier ones
 * decided, so import tax and excise must come before VAT.
 */
final readonly class TaxEngine
{
    /** @var Collection<string, Tax> keyed by tax name */
    private Collection $taxes;

    /** @param iterable<Tax> $taxes */
    public function __construct(
        private Container $container,
        private YearToDate $ledger,
        private Events $events,
        private Bus $bus,
        iterable $taxes,
    ) {
        $this->taxes = new Collection($taxes)->keyBy(fn (Tax $tax) => $tax->name());
    }

    public function process(TaxableEvent $event): TaxReport
    {
        $report = $this->assess($event);

        $this->ledger->record($event, $report);
        $this->events->dispatch(new TaxableEventAssessed($report));

        $vat = $report->for('vat');
        if ($vat instanceof Taxable && $vat->basis()->ref === '165.1') {
            $this->events->dispatch(new VatRegistrationRequired($vat->taxpayer, $vat, $report));
        }

        return $report;
    }

    /** What every tax says, without recording the event — a quote, a preview, a what-if. */
    public function assess(TaxableEvent $event): TaxReport
    {
        return new Pipeline($this->container)
            ->send(new TaxReport($event))
            ->through($this->taxes->values()->all())
            ->thenReturn();
    }

    /** Process on the queue. */
    public function queue(TaxableEvent $event): mixed
    {
        return $this->bus->dispatch(new AssessTaxableEvent($event));
    }

    /** The same engine with only some taxes, in their configured order. */
    public function only(string ...$names): self
    {
        $unknown = array_diff($names, $this->taxes->keys()->all());

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown tax: ' . implode(', ', $unknown) . '. Known: ' . $this->taxes->keys()->implode(', ') . '.');
        }

        return new self($this->container, $this->ledger, $this->events, $this->bus, $this->taxes->only($names));
    }

    public function tax(string $name): Tax
    {
        return $this->taxes->get($name) ?? throw new InvalidArgumentException("Unknown tax: $name.");
    }

    /** @return Collection<string, Tax> */
    public function taxes(): Collection
    {
        return $this->taxes;
    }

    public function ledger(): YearToDate
    {
        return $this->ledger;
    }
}
