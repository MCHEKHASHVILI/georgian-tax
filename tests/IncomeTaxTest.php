<?php

declare(strict_types=1);

namespace GeorgianTax\Tests;

use DateTimeImmutable;
use GeorgianTax\Domain\Asset\Asset;
use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Inherit;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Regime;
use GeorgianTax\Domain\Party\Relief;
use GeorgianTax\Domain\Party\Residency;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\Engine\Outcome\NotTaxable;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\Outcome\Unresolved;
use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\Engine\TaxReport;
use GeorgianTax\IncomeTax\IncomeTax;
use GeorgianTax\Law\Article;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Rates\ConfigRateTable;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/** One test per row of docs/CATALOGUE.md. */
final class IncomeTaxTest extends TestCase
{
    private InMemoryLedger $ledger;
    private TaxEngine $engine;
    private DateTimeImmutable $today;
    private Party $company;
    private Party $person;

    protected function setUp(): void
    {
        $this->ledger = new InMemoryLedger();
        $this->engine = new TaxEngine($this->ledger, IncomeTax::standard($this->ledger, ConfigRateTable::standard()));
        $this->today = new DateTimeImmutable('2026-10-30');
        $this->company = Party::company('acme');
        $this->person = Party::person('nino');
    }

    /* ── Salary ── */

    public function testSalaryIsWithheldAtTwentyPercent(): void
    {
        $report = $this->process($this->pay($this->company, $this->person, K::Salary, 3000));

        $this->assertTaxed($report->for('income'), '154.1.ა', '20%', Collection::WithheldAtSource, 'acme', 600);
        $this->assertSame(240000, $report->netToRecipient()->tetri);
    }

    public function testInternationalCompanySalaryIsFivePercent(): void
    {
        $employer = Party::company('intl', Status::InternationalCompany);

        $this->assertTaxed($this->income($this->pay($employer, $this->person, K::Salary, 1000)), '23.7', '5%', Collection::WithheldAtSource);
    }

    public function testFreeZoneSalaryIsLeftToTheGovernmentResolution(): void
    {
        $outcome = $this->income($this->pay(Party::company('fiz', Status::FreeIndustrialZone), $this->person, K::Salary, 1000));

        $this->assertInstanceOf(Unresolved::class, $outcome);
        $this->assertSame('153.9', $outcome->basis()->ref);
    }

    public function testNonResidentEmployeeIsWithheldUnderArticle134(): void
    {
        $outcome = $this->income($this->pay($this->company, Party::foreigner('john', 'US'), K::Salary, 1000));

        $this->assertTaxed($outcome, '134.1.ვ', '20%', Collection::WithheldAtSource, 'acme');
    }

    public function testShortStayForForeignEmployerIsExempt(): void
    {
        $event = new Pay(Party::foreignCompany('gmbh', 'DE'), Party::foreigner('hans', 'DE'), K::Salary, Money::gel(5000), $this->today, daysWorkedInGeorgia: 20);

        $this->assertExempt($this->income($event), '82.1.შ');
    }

    public function testForeignEmployerWithoutPeLeavesTheEmployeeToPay(): void
    {
        $outcome = $this->income($this->pay(Party::foreignCompany('gmbh', 'DE'), $this->person, K::Salary, 1000));

        $this->assertTaxed($outcome, '154.1.ა', '20%', Collection::MonthlyDeclaration);
    }

    public function testSmallBusinessFirstSixThousandOfSalariesIsNotTaxed(): void
    {
        $employer = Party::person('shop')->withKind(PartyKind::IndividualEntrepreneur)->with(Regime::SmallBusiness);

        $this->assertExempt($this->income($this->pay($employer, $this->person, K::Salary, 1500)), '94.4');
    }

    /* ── Services and rent ── */

    public function testServiceFromAnIndividualIsWithheld(): void
    {
        $this->assertTaxed($this->income($this->pay($this->company, $this->person, K::ServiceFee, 1000)), '154.1.დ', '20%', Collection::WithheldAtSource, 'acme');
    }

    public function testVatRegisteredIndividualDeclaresServiceIncome(): void
    {
        $this->assertTaxed($this->income($this->pay($this->company, Party::person('vat', Status::VatPayer), K::ServiceFee, 1000)), '154.1.დ', '20%', Collection::AnnualDeclaration);
    }

    public function testMicroBusinessPaysNoIncomeTax(): void
    {
        $this->assertExempt($this->income($this->pay($this->company, $this->person->with(Regime::MicroBusiness), K::ServiceFee, 1000)), '86');
    }

    public function testSmallBusinessPaysOnePercent(): void
    {
        $small = $this->person->withKind(PartyKind::IndividualEntrepreneur)->with(Regime::SmallBusiness);

        $this->assertTaxed($this->income($this->pay($this->company, $small, K::ServiceFee, 1000)), '90.1', '1%', Collection::MonthlyDeclaration, null, 10);
    }

    public function testSmallBusinessPaysThreePercentAboveTheThreshold(): void
    {
        $small = $this->person->withKind(PartyKind::IndividualEntrepreneur)->with(Regime::SmallBusiness);
        $this->ledger->seed($small, null, K::ServiceFee, 2026, Money::gel(499_000));

        $this->assertTaxed($this->income($this->pay($this->company, $small, K::ServiceFee, 5000)), '90.2', '3%', Collection::MonthlyDeclaration);
    }

    public function testResidentialRentWithoutDeductionsIsFivePercent(): void
    {
        $event = new Pay($this->company, $this->person, K::ResidentialRent, Money::gel(1000), $this->today, noDeductionsOnResidentialRent: true);

        $this->assertTaxed($this->income($event), '154.1.დ', '5%', Collection::WithheldAtSource);
    }

    public function testRentFromAPrivateTenantIsDeclared(): void
    {
        $this->assertTaxed($this->income($this->pay(Party::person('tenant'), $this->person, K::Rent, 800)), '154.1.დ', '20%', Collection::AnnualDeclaration);
    }

    /* ── Dividends and interest ── */

    public function testDividendIsFivePercent(): void
    {
        $this->assertTaxed($this->income($this->pay($this->company, $this->person, K::Dividend, 1000)), '130.1', '5%', Collection::WithheldAtSource);
    }

    public function testBankDividendFromRecentProfitsIsExempt(): void
    {
        $event = new Pay(Party::company('bank', Status::CreditInstitution), $this->person, K::Dividend, Money::gel(1000), $this->today, profitYear: 2024);

        $this->assertExempt($this->income($event), '130.8');
    }

    public function testDepositInterestFromABankIsExempt(): void
    {
        $this->assertExempt($this->income($this->pay(Party::company('bank', Status::LicensedFinancialInstitution), $this->person, K::Interest, 100)), '131.5');
    }

    public function testInterestFromACompanyIsFivePercent(): void
    {
        $this->assertTaxed($this->income($this->pay($this->company, $this->person, K::Interest, 100)), '131.1', '5%', Collection::WithheldAtSource);
    }

    /* ── Non-residents ── */

    public function testForeignCompanyServingAGeorgianCompanyFromAbroadIsWithheldTenPercent(): void
    {
        $event = new Pay($this->company, Party::foreignCompany('gmbh', 'DE'), K::ServiceFee, Money::gel(1000), $this->today, Place::abroad('DE'));

        $this->assertTaxed($this->income($event), '134.1.ე', '10%', Collection::WithheldAtSource, 'acme', 100);
    }

    public function testForeignPeAbroadTakesTheServiceOutOfGeorgianSource(): void
    {
        $event = new Pay($this->company, Party::foreignCompany('gmbh', 'DE'), K::ServiceFee, Money::gel(1000), $this->today, Place::abroad('DE', providerProvedForeignPE: true));

        $this->assertNotTaxable($this->income($event), '79.ბ');
    }

    public function testPreferentialTaxCountryIsFifteenPercent(): void
    {
        $offshore = Party::foreignCompany('ltd', 'VG', Status::PreferentialTaxJurisdiction);
        $event = new Pay($this->company, $offshore, K::ServiceFee, Money::gel(1000), $this->today, Place::abroad('VG'));

        $this->assertTaxed($this->income($event), '134.1.ე', '15%', Collection::WithheldAtSource);
    }

    public function testNonResidentRoyaltyIsFivePercent(): void
    {
        $this->assertTaxed($this->income($this->pay($this->company, Party::foreignCompany('ip', 'NL'), K::Royalty, 1000)), '134.1.ბ¹', '5%', Collection::WithheldAtSource);
    }

    public function testInternationalTransportIsTenPercent(): void
    {
        $this->assertTaxed($this->income($this->pay($this->company, Party::foreignCompany('ship', 'TR'), K::InternationalTransport, 1000)), '134.1.გ', '10%', Collection::WithheldAtSource);
    }

    public function testNonResidentWithAGeorgianPeIsNotWithheld(): void
    {
        $withPe = new Party('branch', PartyKind::Company, Residency::NonResident, 'DE', hasGeorgianPE: true);

        $this->assertNotTaxable($this->income($this->pay($this->company, $withPe, K::ServiceFee, 1000)), '134.1');
    }

    public function testNonResidentSellingAGeorgianFlatDeclaresTheGain(): void
    {
        $flat = new Asset(AssetType::Dwelling, new DateTimeImmutable('2025-06-01'), Money::gel(100_000));
        $sale = new Sell(Party::foreigner('john', 'US'), $this->person, $flat, Money::gel(120_000), $this->today);

        $this->assertTaxed($this->income($sale), '80.4', '5%', Collection::AnnualDeclaration, null, 1000);
    }

    /* ── Scope ── */

    public function testResidentCompanyPaysProfitTaxNotIncomeTax(): void
    {
        $this->assertNotTaxable($this->income($this->pay($this->company, Party::company('client'), K::ServiceFee, 1000)), '96');
    }

    public function testResidentsForeignSourceIncomeIsExempt(): void
    {
        $event = new Pay(Party::foreignCompany('gmbh', 'DE'), $this->person, K::ServiceFee, Money::gel(1000), $this->today, Place::abroad('DE'));

        $this->assertExempt($this->income($event), '82.1.ფ');
    }

    /* ── Selling assets ── */

    public function testFlatHeldOverTwoYearsIsExempt(): void
    {
        $this->assertExempt($this->income($this->sell(AssetType::Dwelling, '2023-01-01', 100_000, 150_000)), '82.1.ვ.ა');
    }

    public function testFlatHeldUnderTwoYearsIsFivePercentOfTheGain(): void
    {
        $this->assertTaxed($this->income($this->sell(AssetType::Dwelling, '2025-06-01', 100_000, 150_000)), '81.3', '5%', Collection::MonthlyDeclaration, null, 2500);
    }

    public function testCarHeldUnderSixMonthsIsFivePercent(): void
    {
        $this->assertTaxed($this->income($this->sell(AssetType::Car, '2026-08-01', 20_000, 22_000)), '81.3', '5%', Collection::MonthlyDeclaration, null, 100);
    }

    public function testSharesHeldUnderTwoYearsAreTwentyPercentMonthly(): void
    {
        $this->assertTaxed($this->income($this->sell(AssetType::Shares, '2026-01-01', 10_000, 15_000)), '153.12', '20%', Collection::MonthlyDeclaration, null, 1000);
    }

    public function testSaleAtALossIsNotTaxable(): void
    {
        $this->assertNotTaxable($this->income($this->sell(AssetType::Shares, '2026-01-01', 10_000, 8_000)), '82.4.ბ');
    }

    /* ── Gifts and inheritance ── */

    public function testGiftFromAParentIsExempt(): void
    {
        $this->assertExempt($this->income(new Gift(Party::person('mum'), $this->person, Money::gel(50_000), $this->today, HeirOrder::First)), '82.1.ზ');
    }

    public function testGiftsOverOneThousandAYearAreDeclared(): void
    {
        $this->assertExempt($this->process(new Gift(Party::person('friend'), $this->person, Money::gel(800), $this->today))->for('income'), '82.1.თ');

        $second = $this->income(new Gift(Party::person('friend'), $this->person, Money::gel(500), $this->today));

        $this->assertTaxed($second, '80.1, 153.6', '20%', Collection::AnnualDeclaration);
    }

    public function testGiftFromAnEmployerIsWithheld(): void
    {
        $gift = new Gift($this->company, $this->person, Money::gel(500), $this->today, fromEmployer: true);

        $this->assertTaxed($this->income($gift), '154.1.მ', '20%', Collection::WithheldAtSource, 'acme');
    }

    public function testInheritanceFromAnAuntAbove150kIsPaidOverTwoYears(): void
    {
        $this->assertExempt($this->income(new Inherit($this->person, Money::gel(100_000), $this->today, HeirOrder::Fourth)), '82.1.ი');
        $this->assertTaxed($this->income(new Inherit($this->person, Money::gel(200_000), $this->today, HeirOrder::Fourth)), '153.7', '20%', Collection::OverTwoYears);
    }

    /* ── Winnings, pensions, other ── */

    public function testSmallLotteryWinIsExemptLargeOneWithheld(): void
    {
        $lottery = Party::company('lotto');

        $this->assertExempt($this->income($this->pay($lottery, $this->person, K::LotteryWin, 800)), '82.1.ღ');
        $this->assertTaxed($this->income($this->pay($lottery, $this->person, K::LotteryWin, 5000)), '154.1.ე', '20%', Collection::WithheldAtSource);
    }

    public function testGamingWithdrawalIsFivePercentButExemptForForeigners(): void
    {
        $casino = Party::company('casino');

        $this->assertTaxed($this->income($this->pay($casino, $this->person, K::GamingAccountWithdrawal, 1000)), '154.1.ე¹', '5%', Collection::WithheldAtSource);
        $this->assertExempt($this->income($this->pay($casino, new Party('tom', PartyKind::Individual, citizenship: 'GB'), K::GamingAccountWithdrawal, 1000)), '82.1.ძ¹');
    }

    public function testStatePensionIsExemptPrivatePensionWithheld(): void
    {
        $this->assertExempt($this->income($this->pay(Party::state(), $this->person, K::StatePension, 350)), '82.1.ბ');
        $this->assertTaxed($this->income($this->pay(Party::company('fund', Status::PensionProvider), $this->person, K::Pension, 500)), '154.1.ბ', '20%', Collection::WithheldAtSource);
    }

    public function testAlimonyIsExempt(): void
    {
        $this->assertExempt($this->income($this->pay(Party::person('ex'), $this->person, K::Alimony, 500)), '82.1.დ');
    }

    public function testTouristEnterprisePayoutIsFivePercent(): void
    {
        $hotel = Party::company('hotel', Status::TouristEnterprise);

        $this->assertTaxed($this->income($this->pay($hotel, $this->person, K::TouristHotelPayout, 2000)), '133².1', '5%', Collection::WithheldAtSource);
    }

    public function testListedGoodsWithoutWaybillAreThreePercent(): void
    {
        $event = new Pay($this->company, $this->person, K::GoodsSupply, Money::gel(1000), $this->today, listedGoods: true);

        $this->assertTaxed($this->income($event), '133³.1', '3%', Collection::WithheldAtSource);
    }

    public function testBrokerWithholdsOnSecuritiesGain(): void
    {
        $shares = new Asset(AssetType::Shares, new DateTimeImmutable('2026-01-01'), Money::gel(1000));
        $sale = new Sell($this->person, Party::person('buyer'), $shares, Money::gel(1500), $this->today, Party::company('broker', Status::Broker));

        $this->assertTaxed($this->income($sale), '154.1.ლ', '20%', Collection::WithheldAtSource, 'broker', 100);
    }

    /* ── Allowances and refunds ── */

    public function testSingleParentAllowanceShelters3000AYear(): void
    {
        $parent = Party::person('mother', Relief::SingleParent);

        $first = $this->process($this->pay($this->company, $parent, K::Salary, 2000))->for('income');
        $second = $this->income($this->pay($this->company, $parent, K::Salary, 3000));

        $this->assertSame(0, $first->amount->tetri);
        $this->assertSame(200000, $second->base->tetri);   // 1,000 of the 3,000 still sheltered
        $this->assertSame(40000, $second->amount->tetri);
    }

    public function testRefundReversesInProportion(): void
    {
        $salary = $this->pay($this->company, $this->person, K::Salary, 3000);
        $refund = $this->income(new Refund($salary, $this->today, Money::gel(1500)));

        $this->assertInstanceOf(Taxable::class, $refund);
        $this->assertSame(-30000, $refund->amount->tetri);
    }

    /* ── Traceability ── */

    public function testEveryRuleExemptionAndSourceRuleCitesAnArticle(): void
    {
        $root = dirname(__DIR__) . '/src/IncomeTax';
        $checked = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->getExtension() !== 'php' || !preg_match('#/(Rule|Source)/#', $file->getPathname())) {
                continue;
            }

            $class = 'GeorgianTax\\IncomeTax\\' . str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($root) + 1));
            $reflection = new ReflectionClass($class);

            if ($reflection->isInterface()) {
                continue;
            }

            $this->assertNotEmpty($reflection->getAttributes(Article::class), "$class has no #[Article].");
            $checked++;
        }

        $this->assertGreaterThan(20, $checked);
    }

    /* ── Helpers ── */

    private function pay(Party $payer, Party $payee, K $kind, int $gel): Pay
    {
        return new Pay($payer, $payee, $kind, Money::gel($gel), $this->today);
    }

    private function sell(AssetType $type, string $acquired, int $cost, int $price): Sell
    {
        return new Sell($this->person, Party::person('buyer'), new Asset($type, new DateTimeImmutable($acquired), Money::gel($cost)), Money::gel($price), $this->today);
    }

    private function process(TaxableEvent $event): TaxReport
    {
        return $this->engine->process($event);
    }

    private function income(TaxableEvent $event): Outcome
    {
        return $this->process($event)->for('income');
    }

    private function assertTaxed(Outcome $outcome, string $article, string $rate, Collection $collection, ?string $agent = null, ?int $taxGel = null): void
    {
        $this->assertInstanceOf(Taxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
        $this->assertSame($rate, (string) $outcome->rate, (string) $outcome);
        $this->assertSame($collection, $outcome->collection, (string) $outcome);

        if ($agent !== null) {
            $this->assertSame($agent, $outcome->agent?->id, (string) $outcome);
        }

        if ($taxGel !== null) {
            $this->assertSame($taxGel * 100, $outcome->amount->tetri, (string) $outcome);
        }
    }

    private function assertExempt(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(Exempt::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }

    private function assertNotTaxable(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(NotTaxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }
}
