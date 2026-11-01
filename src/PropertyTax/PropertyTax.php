<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax;

use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use GeorgianTax\Engine\LawTables;
use GeorgianTax\Engine\Tax;
use Mchekhashvili\TaxLegislation\Rates\ThresholdTable;
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
 * decisions as MunicipalRates, or take the Code's caps and base rates.
 */
final class PropertyTax extends Tax
{
    public function __construct(
        Container $container,
        Config $config,
        private readonly LawTables $tables,
        private readonly MunicipalRates $rates,
        private readonly ThresholdTable $thresholds,
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
        return new Holding($event, $this->tables->land($event->date())->table, $this->rates, $this->thresholds);
    }
}
