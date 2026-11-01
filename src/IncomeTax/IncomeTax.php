<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax;

use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use GeorgianTax\Engine\Tax;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use GeorgianTax\IncomeTax\Exemption as E;
use GeorgianTax\IncomeTax\Rule\Declared as D;
use GeorgianTax\IncomeTax\Rule\NonResident as N;
use GeorgianTax\IncomeTax\Rule\Withholding as W;
use GeorgianTax\IncomeTax\Source as S;
use GeorgianTax\Ledger\YearToDate;
use Mchekhashvili\TaxLegislation\Rates\RateTable;
use Mchekhashvili\TaxLegislation\Rates\ThresholdTable;

/**
 * Income tax as one stage of the engine, and a pipeline of its own:
 *
 *   IdentifyTaxpayer      Art. 79    individuals only; companies → profit tax
 *   ResolveSource         Art. 104   Georgian source? foreign → 82.1.ფ / 79.ბ
 *   ApplyExemptions       Art. 82    first matching exemption wins
 *   ApplySpecialRegime    Art. 83–95⁶ micro / small business / fixed tax
 *   ApplyAnnualAllowance  Art. 82.2  wraps the rules, shelters part of the base
 *   ApplyRules            Art. 80–81, 130–134, 154   most specific rule first
 */
final class IncomeTax extends Tax
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
        return Assessment::TAX;
    }

    protected function stages(): array
    {
        return [
            Stage\IdentifyTaxpayer::class,
            Stage\ResolveSource::class => [
                S\WorkInGeorgia::class,
                S\PaidByResident::class,
                S\InternationalCarriage::class,
                S\PropertyInGeorgia::class,
                S\CrossBorderServiceToResident::class,
                S\PerformedInGeorgia::class,
                S\OtherActivityInGeorgia::class,
            ],
            Stage\ApplyExemptions::class => [
                E\ExemptByNature::class,
                E\StatusExemptions::class,
                E\SalaryExemptions::class,
                E\PassiveIncomeExemptions::class,
                E\HeldAssetGain::class,
                E\ListedSecurities::class,
                E\GiftsAndInheritance::class,
                E\WinningsAndPensions::class,
            ],
            Stage\ApplySpecialRegime::class,
            Stage\ApplyAnnualAllowance::class,
            Stage\ApplyRules::class => [
                // Non-residents without a Georgian PE — Art. 134
                N\NonResidentRoyalty::class,
                N\NonResidentTransportTelecom::class,
                N\NonResidentOilGas::class,
                N\NonResidentRent::class,
                N\NonResidentSalary::class,
                N\NonResidentPropertyGain::class,
                W\Dividend::class,
                W\Interest::class,
                N\NonResidentOtherIncome::class,

                // Withheld at source — Art. 154.1
                W\InternationalCompanySalary::class,
                W\Salary::class,
                W\PensionsAndScholarships::class,
                W\RoyaltyToResident::class,
                W\Winnings::class,
                W\TouristEnterprisePayout::class,
                W\ListedGoodsWithoutWaybill::class,
                W\BrokerSecuritiesGain::class,
                W\GiftFromTaxAgent::class,
                W\ServiceFromIndividual::class,

                // Declared by the taxpayer — Art. 153
                D\AssetGain::class,
                D\GiftOrInheritance::class,
                D\GeneralIncome::class,
            ],
        ];
    }

    protected function subject(TaxableEvent $event, ?TaxReport $report): object
    {
        return new Assessment($event, $this->ledger, $this->rates, $this->thresholds);
    }
}
