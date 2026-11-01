<?php

declare(strict_types=1);

use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Events\TaxableEventAssessed;
use GeorgianTax\Events\VatRegistrationRequired;
use GeorgianTax\Facades\GeorgianTax;
use GeorgianTax\Jobs\AssessTaxableEvent;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Mchekhashvili\Money\Money;

beforeEach(function () {
    $this->shop = Party::company('shop');
    $this->buyer = Party::company('buyer', Status::VatPayer);
    $this->sale = fn (int $gel, string $on = '2026-10-30') => new Pay($this->buyer, $this->shop, IncomeKind::GoodsSupply, Money::GEL($gel), new DateTimeImmutable($on));
});

it('announces every assessment', function () {
    Event::fake([TaxableEventAssessed::class]);

    $report = GeorgianTax::process(($this->sale)(100));

    Event::assertDispatched(TaxableEventAssessed::class, fn (TaxableEventAssessed $e) => $e->report === $report);
});

it('announces nothing for a quote', function () {
    Event::fake();

    GeorgianTax::assess(($this->sale)(100));

    Event::assertNothingDispatched();
});

it('tells the app when a supplier must register for VAT', function () {
    Event::fake([VatRegistrationRequired::class]);

    GeorgianTax::process(($this->sale)(90_000));
    Event::assertNotDispatched(VatRegistrationRequired::class);

    GeorgianTax::process(($this->sale)(20_000, '2026-10-30'));   // a Friday

    Event::assertDispatched(VatRegistrationRequired::class, function (VatRegistrationRequired $e) {
        return $e->supplier->id === 'shop'
            && (string) $e->vat->amount === '3600.00 GEL'
            && $e->deadline()->format('Y-m-d') === '2026-11-03';   // two working days: Mon, Tue
    });
});

it('queues an assessment', function () {
    Bus::fake();

    GeorgianTax::queue(($this->sale)(100));

    Bus::assertDispatched(AssessTaxableEvent::class, fn (AssessTaxableEvent $job) => (string) $job->event->income() === '100.00 GEL');
});

it('puts the job on the configured connection and queue', function () {
    config()->set('georgian-tax.queue', ['connection' => 'redis', 'queue' => 'tax']);

    $job = new AssessTaxableEvent(($this->sale)(100));

    expect($job->connection)->toBe('redis')->and($job->queue)->toBe('tax');
});

it('assesses and records when the job runs', function () {
    config()->set('queue.default', 'sync');
    Event::fake([TaxableEventAssessed::class]);

    GeorgianTax::queue(($this->sale)(100));

    Event::assertDispatchedTimes(TaxableEventAssessed::class, 1);
    expect((string) GeorgianTax::ledger()->turnover($this->shop, 'vat', new DateTimeImmutable('2026-01-01')))->toBe('100.00 GEL');
});

it('survives serialization onto a real queue', function () {
    $job = unserialize(serialize(new AssessTaxableEvent(($this->sale)(100))));

    expect($job->event)->toEqual(($this->sale)(100));
});
