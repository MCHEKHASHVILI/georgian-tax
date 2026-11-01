<?php

declare(strict_types=1);

namespace GeorgianTax\Tests;

use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\GeorgianTaxServiceProvider;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Ledger\YearToDate;
use Orchestra\Testbench\TestCase as Testbench;

/**
 * Boots the package in a Testbench app. Tax tests run on the in-memory ledger; the Laravel tests
 * switch to Eloquent with RefreshDatabase.
 */
abstract class TestCase extends Testbench
{
    protected function getPackageProviders($app): array
    {
        return [GeorgianTaxServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['GeorgianTax' => \GeorgianTax\Facades\GeorgianTax::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('georgian-tax.ledger', 'memory');
        $app['config']->set('georgian-tax.store_reports', false);
    }

    /** The container's engine, optionally narrowed to some taxes. */
    protected function taxEngine(string ...$taxes): TaxEngine
    {
        $engine = $this->app->make(TaxEngine::class);

        return $taxes === [] ? $engine : $engine->only(...$taxes);
    }

    protected function memoryLedger(): InMemoryLedger
    {
        $ledger = $this->app->make(YearToDate::class);
        assert($ledger instanceof InMemoryLedger);

        return $ledger;
    }
}
