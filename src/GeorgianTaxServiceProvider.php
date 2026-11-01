<?php

declare(strict_types=1);

namespace GeorgianTax;

use GeorgianTax\Engine\Tax;
use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\Events\TaxableEventAssessed;
use GeorgianTax\Excise\ExciseTable;
use GeorgianTax\ImportTax\ImportTable;
use GeorgianTax\Ledger\CompanyBooks;
use GeorgianTax\Ledger\Eloquent\EloquentLedger;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Ledger\YearToDate;
use GeorgianTax\Listeners\StoreAssessment;
use GeorgianTax\PropertyTax\LandRates;
use GeorgianTax\PropertyTax\MunicipalRates;
use GeorgianTax\Rates\ConfigRateTable;
use GeorgianTax\Rates\RateTable;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

final class GeorgianTaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/georgian-tax.php', 'georgian-tax');

        // The law: rate tables, read once.
        $this->app->singleton(RateTable::class, fn () => ConfigRateTable::standard($this->law('rates')));
        $this->app->singleton(ExciseTable::class, fn () => ExciseTable::standard($this->law('excise')));
        $this->app->singleton(ImportTable::class, fn () => ImportTable::standard($this->law('import')));
        $this->app->singleton(LandRates::class, fn () => LandRates::standard($this->law('land')));

        // Your councils' property tax decisions, checked against the Code's caps.
        $this->app->singleton(MunicipalRates::class, function () {
            $p = (array) config('georgian-tax.property');

            return new MunicipalRates(
                companyPercent: (float) ($p['company_percent'] ?? 1.0),
                leasingCompanyPercent: (float) ($p['leasing_company_percent'] ?? 0.6),
                individualPercent: (float) ($p['individual_percent'] ?? 1.0),
                landMultipliers: array_change_key_case($p['land_multipliers'] ?? []),
                territorialCoefficients: array_change_key_case($p['territorial_coefficients'] ?? []),
                naturalResourcePerHa: (float) ($p['natural_resource_per_ha'] ?? 3.0),
            );
        });

        // Year-to-date history and company figures.
        $this->app->singleton(YearToDate::class, fn (Container $app) => match (config('georgian-tax.ledger')) {
            'memory' => new InMemoryLedger(),
            default => $app->make(EloquentLedger::class),
        });
        $this->app->bindIf(CompanyBooks::class, fn (Container $app) => $app->make(config('georgian-tax.books')));

        // The engine, with the configured taxes in order.
        $this->app->scoped(TaxEngine::class, fn (Container $app) => new TaxEngine(
            $app,
            $app->make(YearToDate::class),
            $app->make('events'),
            $app->make(Bus::class),
            array_map(fn (string $tax): Tax => $app->make($tax), (array) config('georgian-tax.taxes')),
        ));
    }

    public function boot(Dispatcher $events): void
    {
        if (config('georgian-tax.store_reports') && config('georgian-tax.ledger') !== 'memory') {
            $events->listen(TaxableEventAssessed::class, StoreAssessment::class);
        }

        if (config('georgian-tax.database.run_migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/georgian-tax.php' => config_path('georgian-tax.php')], 'georgian-tax-config');
            $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')], 'georgian-tax-migrations');
            $this->publishes([__DIR__ . '/../resources/law' => resource_path('law/georgian-tax')], 'georgian-tax-law');
        }
    }

    private function law(string $table): ?string
    {
        return config("georgian-tax.law.$table");
    }
}
