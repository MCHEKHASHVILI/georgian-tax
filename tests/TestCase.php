<?php

declare(strict_types=1);

namespace GeorgianTax\Tests;

use Mchekhashvili\TaxLegislation\Engine\TaxEngine;
use GeorgianTax\Facades\GeorgianTax;
use GeorgianTax\GeorgianTaxServiceProvider;
use Mchekhashvili\Countries\CountriesServiceProvider;
use Mchekhashvili\TaxLegislation\Contracts\Ledger;
use Mchekhashvili\TaxLegislation\Engine\TaxRegistry;
use Mchekhashvili\TaxLegislation\TaxLegislationServiceProvider;
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
        return [CountriesServiceProvider::class, TaxLegislationServiceProvider::class, GeorgianTaxServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['GeorgianTax' => GeorgianTax::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('georgian-tax.ledger', 'memory');
        $app['config']->set('tax-legislation.store_reports', false);
    }

    /** The container's engine, optionally narrowed to some taxes. */
    protected function taxEngine(string ...$taxes): TaxEngine
    {
        $engine = $this->app->make(GeorgianTaxServiceProvider::ENGINE);

        return $taxes === [] ? $engine : $engine->only(...$taxes);
    }

    protected function memoryLedger(): InMemoryLedger
    {
        $ledger = $this->app->make(YearToDate::class);
        assert($ledger instanceof InMemoryLedger);

        return $ledger;
    }

    /** Rebuild the engine after changing config or bindings it was built from. */
    protected function refreshEngine(): void
    {
        foreach ([TaxEngine::class, TaxRegistry::class, Ledger::class, GeorgianTaxServiceProvider::ENGINE] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        GeorgianTax::clearResolvedInstance(GeorgianTaxServiceProvider::ENGINE);
    }
}
