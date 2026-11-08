<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use DateTimeImmutable;
use GeorgianTax\Excise\ExciseRules;
use GeorgianTax\Excise\ExciseTable;
use GeorgianTax\ImportTax\ImportTable;
use GeorgianTax\PropertyTax\LandRates;
use GeorgianTax\PropertyTax\PropertyCaps;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Rates\CountryTables;
use OutOfBoundsException;

/**
 * The tables of Georgian law as they stood on a day: the excise rates (188.1) and the figures its
 * rules apply (188.9–17, 188.1¹, 188¹.1, 188.3), the import tax lists
 * (197, 199.პ), the land rates (204) and the caps on the councils' property tax rates (202, 204). From the package's files, or — with
 * config('georgian-tax.law.source') = 'database' — from tax-legislation's history, so a table row
 * a law changed applies from its date.
 */
final class LawTables
{
    /** @var array<string, object> */
    private array $memo = [];

    public function __construct(private readonly Container $app, private readonly Config $config) {}

    public function excise(DateTimeImmutable $on): ExciseTable
    {
        if (!$this->fromDatabase()) {
            return $this->app->make(ExciseTable::class);
        }

        return $this->memo['excise|' . $on->format('Y-m-d')] ??= new ExciseTable(array_map(
            fn (array $v) => [$v['unit'], (float) $v['rate'], $v['description'] ?? ''],
            $this->rows('excise', 'excise.188.1', $on),
        ));
    }

    public function exciseRules(DateTimeImmutable $on): ExciseRules
    {
        if (!$this->fromDatabase()) {
            return $this->app->make(ExciseRules::class);
        }

        return $this->memo['excise-rules|' . $on->format('Y-m-d')] ??= new ExciseRules(array_combine(
            ExciseRules::TABLES,
            array_map(fn (string $table) => $this->rows('excise', $table, $on), ExciseRules::TABLES),
        ));
    }

    public function import(DateTimeImmutable $on): ImportTable
    {
        if (!$this->fromDatabase()) {
            return $this->app->make(ImportTable::class);
        }

        return $this->memo['import|' . $on->format('Y-m-d')] ??= (function () use ($on) {
            $exemptions = $this->rows('import', 'import.199.პ', $on);

            return new ImportTable(
                array_map('strval', array_keys($this->rows('import', 'import.197.1', $on))),
                array_map('strval', array_keys($this->rows('import', 'import.197.2', $on))),
                array_map(fn (array $v) => [(float) $v['euro'], $v['measure'], $v['description'] ?? ''], $this->rows('import', 'import.197.3', $on)),
                array_map('strval', array_keys(array_filter($exemptions, fn (array $v) => $v['exempt'] === true))),
                array_map('strval', array_keys(array_filter($exemptions, fn (array $v) => $v['exempt'] === false))),
            );
        })();
    }

    public function land(DateTimeImmutable $on): LandRates
    {
        if (!$this->fromDatabase()) {
            return $this->app->make(LandRates::class);
        }

        return $this->memo['land|' . $on->format('Y-m-d')] ??= (function () use ($on) {
            $perHa = fn (string $use) => array_map(fn (array $v) => (float) $v['gel_per_ha'], $this->rows('property', "property.204.1.$use", $on));
            $property = TaxType::query()->ofCountry(Tax::COUNTRY)->where('code', 'property')->inForceOn($on)->first()
                ?? throw new OutOfBoundsException('No property tax in force on ' . $on->format('Y-m-d') . '.');
            $amount = fn (string $article) => ($property->rateOn($article, $on)?->amount
                ?? throw new OutOfBoundsException("No property tax rate $article in force on {$on->format('Y-m-d')}."))->toFloat();

            return new LandRates([
                'arable' => $perHa('arable'),
                'hayfield' => $perHa('hayfield'),
                'pasture' => $perHa('pasture'),
                'non_agricultural_per_m2' => $amount('204.2.ა'),
                'natural_resource_per_ha' => $amount('204.3'),
            ]);
        })();
    }

    /** The limits on the councils' property tax rates (202, 204). */
    public function propertyCaps(DateTimeImmutable $on): PropertyCaps
    {
        if (!$this->fromDatabase()) {
            return $this->app->make(PropertyCaps::class);
        }

        return $this->memo['property-caps|' . $on->format('Y-m-d')] ??= new PropertyCaps($this->rows('property', PropertyCaps::TABLE, $on));
    }

    private function fromDatabase(): bool
    {
        return $this->config->get('georgian-tax.law.source') === 'database';
    }

    /** @return array<string, array<string, mixed>> */
    private function rows(string $tax, string $table, DateTimeImmutable $on): array
    {
        return $this->app->make(CountryTables::class)->tables(Tax::COUNTRY)->rows($tax, $table, $on);
    }
}
