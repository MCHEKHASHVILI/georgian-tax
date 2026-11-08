<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax;

use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use GeorgianTax\Engine\LawTables;
use GeorgianTax\Engine\Tax;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use GeorgianTax\PropertyTax\Rule as R;

/**
 * Property tax — the local tax — as one stage of the engine, and a pipeline of its own:
 *
 *   IdentifyTaxpayer  Art. 201, 203  who pays on what
 *   ApplyExemptions   Art. 206       by owner, by item, and the 206.2 leasing carve-out
 *   rate rules        Art. 202, 204  land per hectare or m²; company and individual property by value
 *
 * Feed it an OwnProperty event per item per year. Rates are municipal: pass the council's
 * decisions as MunicipalRates, or take the Code's caps and base rates. The law is read on the
 * day the Code fixes the rate: 31 December of the tax year for property (202.8), 1 April for
 * land (204.4) — the land rates and the caps (PropertyCaps) alike.
 */
final class PropertyTax extends Tax
{
    public function __construct(
        Container $container,
        Config $config,
        private readonly LawTables $tables,
        private readonly MunicipalRates $rates,
    ) {
        parent::__construct($container, $config);
    }

    public function name(): string
    {
        return Holding::TAX;
    }

    protected function stages(): array
    {
        return [
            Stage\IdentifyTaxpayer::class,
            Stage\ApplyExemptions::class,
            Stage\ApplyRules::class => [R\AgriculturalLand::class, R\OtherLand::class, R\CompanyProperty::class, R\IndividualProperty::class],
        ];
    }

    protected function subject(TaxableEvent $event, ?TaxReport $report): object
    {
        $propertyDay = $event->date();                  // 202.8: 31 December of the tax year
        $landDay = Holding::landDayOf($event->date());  // 204.4: 1 April
        $land = $this->tables->land($landDay)->table;
        $landCaps = $this->tables->propertyCaps($landDay)->with('204.3', ['unit' => ' GEL/ha', 'max' => $land['natural_resource_per_ha']]);

        return new Holding($event, $land, $this->rates, $this->thresholdTable(), $this->tables->propertyCaps($propertyDay), $landCaps);
    }
}
