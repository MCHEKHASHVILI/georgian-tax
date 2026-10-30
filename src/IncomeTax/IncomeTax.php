<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax;

use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Pipeline\Pipeline;
use GeorgianTax\Engine\Tax;
use GeorgianTax\IncomeTax\Exemption as E;
use GeorgianTax\IncomeTax\Rule\Declared as D;
use GeorgianTax\IncomeTax\Rule\NonResident as N;
use GeorgianTax\IncomeTax\Rule\Withholding as W;
use GeorgianTax\IncomeTax\Source as S;
use GeorgianTax\IncomeTax\Stage;
use GeorgianTax\Ledger\YearToDate;
use GeorgianTax\Rates\RateTable;
use LogicException;

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
        private readonly Pipeline $pipeline,
        private readonly YearToDate $ledger,
        private readonly RateTable $rates,
    ) {}

    public static function standard(YearToDate $ledger, RateTable $rates): self
    {
        $stages = [
            new Stage\IdentifyTaxpayer(),
            new Stage\ResolveSource([
                new S\WorkInGeorgia(),
                new S\PaidByResident(),
                new S\InternationalCarriage(),
                new S\PropertyInGeorgia(),
                new S\CrossBorderServiceToResident(),
                new S\PerformedInGeorgia(),
                new S\OtherActivityInGeorgia(),
            ]),
            new Stage\ApplyExemptions([
                new E\ExemptByNature(),
                new E\StatusExemptions(),
                new E\SalaryExemptions(),
                new E\PassiveIncomeExemptions(),
                new E\HeldAssetGain(),
                new E\ListedSecurities(),
                new E\GiftsAndInheritance(),
                new E\WinningsAndPensions(),
            ]),
            new Stage\ApplySpecialRegime(),
            new Stage\ApplyAnnualAllowance(),
            new Stage\ApplyRules([
                // Non-residents without a Georgian PE — Art. 134
                new N\NonResidentRoyalty(),
                new N\NonResidentTransportTelecom(),
                new N\NonResidentOilGas(),
                new N\NonResidentRent(),
                new N\NonResidentSalary(),
                new N\NonResidentPropertyGain(),
                new W\Dividend(),
                new W\Interest(),
                new N\NonResidentOtherIncome(),

                // Withheld at source — Art. 154.1
                new W\InternationalCompanySalary(),
                new W\Salary(),
                new W\PensionsAndScholarships(),
                new W\RoyaltyToResident(),
                new W\Winnings(),
                new W\TouristEnterprisePayout(),
                new W\ListedGoodsWithoutWaybill(),
                new W\BrokerSecuritiesGain(),
                new W\GiftFromTaxAgent(),
                new W\ServiceFromIndividual(),

                // Declared by the taxpayer — Art. 153
                new D\AssetGain(),
                new D\GiftOrInheritance(),
                new D\GeneralIncome(),
            ]),
        ];

        $unreachable = static fn () => throw new LogicException('ApplyRules always answers.');

        return new self(new Pipeline($stages, $unreachable), $ledger, $rates);
    }

    public function name(): string
    {
        return Assessment::TAX;
    }

    public function assess(TaxableEvent $event): Outcome
    {
        if ($event instanceof Refund) {
            return $this->assess($event->original)->reversed($event->share());
        }

        return $this->pipeline->process(new Assessment($event, $this->ledger, $this->rates));
    }
}
