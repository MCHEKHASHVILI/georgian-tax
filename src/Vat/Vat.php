<?php

declare(strict_types=1);

namespace GeorgianTax\Vat;

use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use GeorgianTax\Engine\Tax;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use GeorgianTax\Ledger\YearToDate;
use Mchekhashvili\TaxLegislation\Rates\RateTable;
use Mchekhashvili\TaxLegislation\Rates\ThresholdTable;
use GeorgianTax\Vat\Exemption as E;
use GeorgianTax\Vat\Place as P;
use GeorgianTax\Vat\Rule as R;

/**
 * VAT as one stage of the engine, and a pipeline of its own:
 *
 *   IdentifyOperation     Art. 158–160³  a supply, an import, or a deemed supply?
 *   ResolvePlaceOfSupply  Art. 162–162¹  supplied in Georgia?
 *   ApplyExemptions       Art. 170–173   exempt with credit, without credit, or exempt import
 *   ApplyLiability        Art. 161, 165, 168   who pays, and when
 *
 * A refund is the original assessment reversed in proportion (Art. 179 adjustment).
 */
final class Vat extends Tax
{
    public function __construct(
        Container $container,
        Config $config,
        private readonly YearToDate $ledger,
        private readonly RateTable $rates,
        private readonly ThresholdTable $thresholds,
    ) {
        parent::__construct($container, $config);
    }

    public function name(): string
    {
        return Supply::TAX;
    }

    protected function stages(): array
    {
        return [
            Stage\IdentifyOperation::class,
            Stage\ResolvePlaceOfSupply::class => [
                P\GoodsLocation::class,
                P\ImmovableProperty::class,
                P\WherePerformed::class,
                P\ConsumerLocation::class,
                P\BusinessCustomer::class,
                P\SupplierLocation::class,
            ],
            Stage\ApplyExemptions::class => [
                E\ExemptImports::class,
                E\Export::class,
                E\PartyExemptions::class,
                E\ExemptCategories::class,
            ],
            Stage\ApplyLiability::class => [
                R\ImportVat::class,
                R\ReverseCharge::class,
                R\ForeignSupplierToConsumer::class,
                R\RegisteredSupplier::class,
                R\ObligedFromFirstSupply::class,
                R\RegistrationThreshold::class,
            ],
        ];
    }

    protected function subject(TaxableEvent $event, ?TaxReport $report): object
    {
        return new Supply($event, $this->ledger, $this->rates, $this->thresholds, $report?->amountOf('excise', 'import'));
    }
}
