<?php

declare(strict_types=1);

namespace GeorgianTax;

use GeorgianTax\Engine\Tax;
use GeorgianTax\Excise\ExciseTable;
use GeorgianTax\ImportTax\ImportTable;
use GeorgianTax\Ledger\CompanyBooks;
use GeorgianTax\Ledger\Eloquent\EloquentLedger;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Ledger\YearToDate;
use GeorgianTax\Listeners\FlagVatRegistration;
use GeorgianTax\PropertyTax\LandRates;
use GeorgianTax\PropertyTax\MunicipalRates;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Contracts\Ledger;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\TaxEngine;
use Mchekhashvili\TaxLegislation\Engine\TaxRegistry;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use Mchekhashvili\TaxLegislation\Events\TaxableEventAssessed;
use Mchekhashvili\TaxLegislation\Rates\ConfigRateTable;
use Mchekhashvili\TaxLegislation\Rates\EloquentRateTable;
use Mchekhashvili\TaxLegislation\Rates\RateTable;

/**
 * Georgia's law on top of mchekhashvili/tax-legislation: registers Georgia's six taxes with the
 * engine, binds the Georgian ledger and tables, and adds Georgian questions to the TaxReport.
 */
final class GeorgianTaxServiceProvider extends ServiceProvider
{
    public const ENGINE = 'georgian-tax.engine';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/georgian-tax.php', 'georgian-tax');

        // Georgia's taxes, in order, into the shared engine.
        $this->callAfterResolving(TaxRegistry::class, fn (TaxRegistry $registry) => $registry->register(...(array) config('georgian-tax.taxes')));

        // The law: rates from the database (with their history) or from the package's files.
        $this->app->singleton(RateTable::class, fn () => config('georgian-tax.law.source') === 'database'
            ? new EloquentRateTable(Tax::COUNTRY)
            : ConfigRateTable::standard($this->law('rates') ?? __DIR__ . '/../resources/law/rates.php'));
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

        // Year-to-date history — the engine's ledger — and company figures.
        $this->app->singleton(YearToDate::class, fn (Container $app) => match (config('georgian-tax.ledger')) {
            'memory' => new InMemoryLedger(),
            default => $app->make(EloquentLedger::class),
        });
        $this->app->singleton(Ledger::class, fn (Container $app) => $app->make(YearToDate::class));
        $this->app->bindIf(CompanyBooks::class, fn (Container $app) => $app->make(config('georgian-tax.books')));

        // The engine narrowed to Georgia — what the GeorgianTax facade resolves.
        $this->app->scoped(self::ENGINE, fn (Container $app) => $app->make(TaxEngine::class)->forCountry(Tax::COUNTRY));
    }

    public function boot(Dispatcher $events): void
    {
        $events->listen(TaxableEventAssessed::class, FlagVatRegistration::class);

        /** VAT the supplier adds on top of the price — what the customer pays beyond the event amount. */
        TaxReport::macro('vatCharged', function (): Money {
            /** @var TaxReport $this */
            $vat = $this->for('vat');

            return $vat instanceof Taxable && $vat->collection === Collection::MonthlyDeclaration ? $vat->amount : Money::GEL(0);
        });

        /** What the payer actually transfers to the recipient after withholding. */
        TaxReport::macro('netToRecipient', function (): Money {
            /** @var TaxReport $this */
            return $this->charges()
                ->filter(fn ($o) => $o instanceof Taxable && $o->collection === Collection::WithheldAtSource)
                ->reduce(fn (Money $net, Taxable $o) => $net->minus($o->amount), $this->event->income());
        });

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
