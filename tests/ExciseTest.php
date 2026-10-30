<?php

declare(strict_types=1);

namespace GeorgianTax\Tests;

use DateTimeImmutable;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Goods\ImportRelief;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Income\SupplyCategory;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\Engine\Outcome\ExemptWithCredit;
use GeorgianTax\Engine\Outcome\Levied;
use GeorgianTax\Engine\Outcome\NotTaxable;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\Engine\TaxReport;
use GeorgianTax\Excise\Excise;
use GeorgianTax\Excise\Rate\Calls;
use GeorgianTax\Law\Article;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Rates\ConfigRateTable;
use GeorgianTax\Vat\Vat;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/** One test per row of the excise section of docs/CATALOGUE.md. */
final class ExciseTest extends TestCase
{
    private TaxEngine $engine;
    private DateTimeImmutable $today;
    private Party $importer;
    private Party $producer;
    private Party $shop;

    protected function setUp(): void
    {
        $ledger = new InMemoryLedger();
        $this->engine = new TaxEngine($ledger, Excise::standard(), Vat::standard($ledger, ConfigRateTable::standard()));
        $this->today = new DateTimeImmutable('2026-10-30');
        $this->importer = Party::company('importer', Status::VatPayer);
        $this->producer = Party::company('distillery', Status::VatPayer, Status::ExciseProducer);
        $this->shop = Party::company('shop', Status::VatPayer);
    }

    /* ── Who pays, on what ── */

    public function testNoGoodsNoExcise(): void
    {
        $this->assertNotTaxable($this->excise(new Pay($this->shop, $this->importer, K::ServiceFee, Money::gel(100), $this->today)), '183');
    }

    public function testNonExciseGoodsAreNotTaxable(): void
    {
        $this->assertNotTaxable($this->excise($this->import(new Commodity('0901 21', 100))), '188');
    }

    public function testResellerPaysNoExcise(): void
    {
        $sale = $this->sell($this->shop, new Commodity('2208 30', 10, alcoholPercent: 40));

        $this->assertNotTaxable($this->excise($sale), '182–184');
    }

    public function testProducerSupplyIsDueByThe15thOfNextMonth(): void
    {
        $duty = $this->excise($this->sell($this->producer, new Commodity('2208 30', 10, alcoholPercent: 40)));

        $this->assertLevied($duty, '188¹.1', 90);
        $this->assertSame(Collection::MonthlyDeclaration, $duty->collection);
        $this->assertSame('2026-11-15', $duty->due->format('Y-m-d'));
    }

    public function testImportPaysAtCustoms(): void
    {
        $duty = $this->excise($this->import(new Commodity('2710 12', 2)));

        $this->assertLevied($duty, '188.1', 1000);
        $this->assertSame(Collection::AtCustoms, $duty->collection);
        $this->assertSame('importer', $duty->taxpayer->id);
    }

    public function testExciseJoinsTheVatBaseOnImport(): void
    {
        $report = $this->engine->process($this->import(new Commodity('2710 12', 2), customs: 4000));
        $vat = $report->for('vat');

        $this->assertInstanceOf(Taxable::class, $vat);
        $this->assertSame((4000 + 1000) * 18, $vat->amount->tetri);
    }

    /* ── Exemptions (194) ── */

    public function testExportIsExemptWithCredit(): void
    {
        $sale = new Pay(Party::foreignCompany('buyer', 'DE'), $this->producer, K::GoodsSupply, Money::gel(5000), $this->today,
            Place::exportTo('DE'), commodity: new Commodity('2208 20', 100, alcoholPercent: 40));

        $this->assertInstanceOf(ExemptWithCredit::class, $this->excise($sale));
        $this->assertSame('194.6.ა', $this->excise($sale)->basis()->ref);
    }

    public function testDutyFreeSupplyIsExemptWithCredit(): void
    {
        $sale = new Pay($this->shop, $this->producer, K::GoodsSupply, Money::gel(500), $this->today,
            supply: SupplyCategory::DutyFree, commodity: new Commodity('2208 30', 10, alcoholPercent: 40));

        $outcome = $this->excise($sale);
        $this->assertInstanceOf(ExemptWithCredit::class, $outcome);
        $this->assertSame('194.6.ბ', $outcome->basis()->ref);
    }

    public function testElectricLeftHandDriveCarIsExempt(): void
    {
        $this->assertExempt($this->excise($this->import(new Commodity('8703 80', electric: true))), '194.5.ლ');
    }

    public function testFuelInTheTankIsExempt(): void
    {
        $this->assertExempt($this->excise($this->import(new Commodity('2710 19 43', 0.05), relief: ImportRelief::FuelInVehicleTank)), '194.5.გ');
    }

    public function testPostalReliefStopsAboveItsLimit(): void
    {
        $small = $this->import(new Commodity('2208 30', 1, alcoholPercent: 40), customs: 200, relief: ImportRelief::PostalSmall);
        $large = $this->import(new Commodity('2208 30', 1, alcoholPercent: 40), customs: 400, relief: ImportRelief::PostalSmall);

        $this->assertExempt($this->excise($small), '194.5.ბ¹');
        $this->assertInstanceOf(Levied::class, $this->excise($large));
    }

    /* ── Cars (188.9–18) ── */

    public function testNewCarAt150PerCc(): void
    {
        $this->assertLevied($this->excise($this->car(engineCc: 2000, yearMade: 2022)), '188.12', 3000);
    }

    public function testCarOverSixYearsAt450PerCc(): void
    {
        $this->assertLevied($this->excise($this->car(engineCc: 2000, yearMade: 2019)), '188.13', 9000);
    }

    public function testRightHandDriveTriplesTheRate(): void
    {
        $this->assertLevied($this->excise($this->car(engineCc: 2000, yearMade: 2019, rightHandDrive: true)), '188.16', 27_000);
    }

    public function testYoungLeftHandHybridIs60PercentLess(): void
    {
        $this->assertLevied($this->excise($this->car(engineCc: 2000, yearMade: 2023, hybrid: true)), '188.14', 1200);
    }

    public function testOldHybridGetsNoReduction(): void
    {
        $this->assertLevied($this->excise($this->car(engineCc: 2000, yearMade: 2015, hybrid: true)), '188.13', 9000);
    }

    public function testClassicCarAt1PerCc(): void
    {
        $this->assertLevied($this->excise($this->car(engineCc: 3000, yearMade: 1975, classicCar: true)), '188.10', 3000);
    }

    public function testSportsCarIsFlat100(): void
    {
        $this->assertLevied($this->excise($this->car(engineCc: 5000, yearMade: 2024, sportsCar: true)), '188.9', 100);
    }

    public function testRightHandElectricCarIsFlat3000(): void
    {
        $this->assertLevied($this->excise($this->car(electric: true, rightHandDrive: true)), '188.17', 3000);
    }

    public function testCarWithoutYearCannotBeComputed(): void
    {
        $this->assertNotTaxable($this->excise($this->car(engineCc: 2000)), '188.18');
    }

    /* ── Motorcycles (188.1) ── */

    public function testMotorcycleRateFollowsAge(): void
    {
        $this->assertLevied($this->excise($this->import(new Commodity('8711 20', engineCc: 250, yearMade: 2026))), '188.1', 375);
        $this->assertLevied($this->excise($this->import(new Commodity('8711 20', engineCc: 250, yearMade: 2023))), '188.1', 175);
        $this->assertLevied($this->excise($this->import(new Commodity('8711 20', engineCc: 250, yearMade: 2000))), '188.1', 600);
    }

    /* ── Tobacco (188.1, 188.1¹) ── */

    public function testCigarettesPaySpecificPlus20PercentOfRetail(): void
    {
        // 10,000 sticks = 500 packs: 500 × 2.75 + 500 × 8 × 20% = 1,375 + 800
        $this->assertLevied($this->excise($this->import(new Commodity('2402 20', 10_000, retailPricePer20: 8))), '188.1¹.ა', 2175);
    }

    public function testLocalQuotaCigarettes(): void
    {
        $c = new Commodity('2402 20', 10_000, retailPricePer20: 8, localQuotaCigarettes: true);

        // 500 × 1.30 + 500 × 8 × 15% = 650 + 600
        $this->assertLevied($this->excise($this->sell(Party::company('factory', Status::ExciseProducer), $c)), '188.1¹.ა¹', 1250);
    }

    public function testCigarillosPay30PercentOfRetail(): void
    {
        // 200 sticks = 10 packs: 10 × 2.40 + 10 × 10 × 30%
        $this->assertLevied($this->excise($this->import(new Commodity('2402 10 000 02', 200, retailPricePer20: 10))), '188.1¹.გ', 54);
    }

    public function testCigarettesNeedARetailPrice(): void
    {
        $this->assertNotTaxable($this->excise($this->import(new Commodity('2402 20', 1000))), '188.1¹.ა');
    }

    public function testCigarsPerStickFromTheTable(): void
    {
        $this->assertLevied($this->excise($this->import(new Commodity('2402 10 000 01', 100))), '188.1', 210);
    }

    public function testNicotineLiquidPerMl(): void
    {
        $this->assertLevied($this->excise($this->import(new Commodity('2404 12', 500))), '188.1', 600);
    }

    /* ── Alcohol (188¹) ── */

    public function testBeerPerLitrePerDegree(): void
    {
        // 1,000 litres × 5% × 0.12
        $this->assertLevied($this->excise($this->import(new Commodity('2203', 1000, alcoholPercent: 5))), '188¹.1', 600);
    }

    public function testWineCarriesNoExcise(): void
    {
        $this->assertNotTaxable($this->excise($this->import(new Commodity('2204 21', 1000, alcoholPercent: 13))), '188¹.2');
    }

    public function testLightFermentedDrink(): void
    {
        $this->assertLevied($this->excise($this->import(new Commodity('2206 00 310', 1000, alcoholPercent: 4.5))), '188¹.1', 600);
    }

    public function testStrongerFermentedDrink(): void
    {
        $this->assertLevied($this->excise($this->import(new Commodity('2206 00 310', 1000, alcoholPercent: 8))), '188¹.1', 5000);
    }

    public function testEthylAlcoholPerLitreOfPureAlcohol(): void
    {
        // 1,000 litres at 96% = 960 litres of pure alcohol × 7.50
        $this->assertLevied($this->excise($this->import(new Commodity('2207 10', 1000, alcoholPercent: 96))), '188¹.1', 7200);
    }

    public function testBrandyAt15(): void
    {
        $this->assertLevied($this->excise($this->import(new Commodity('2208 20', 100, alcoholPercent: 40))), '188¹.1', 600);
    }

    public function testVodkaAt15AndWhiskyAt2250(): void
    {
        $this->assertLevied($this->excise($this->import(new Commodity('2208 60', 100, alcoholPercent: 40))), '188¹.1', 600);
        $this->assertLevied($this->excise($this->import(new Commodity('2208 30', 100, alcoholPercent: 40))), '188¹.1', 900);
    }

    /* ── Fuels and gases (188.1, 182.1.დ) ── */

    public function testDieselByTheTonne(): void
    {
        $this->assertLevied($this->excise($this->import(new Commodity('2710 19 43', 10))), '188.1', 4000);
    }

    public function testBilgeWaterAt100(): void
    {
        $this->assertLevied($this->excise($this->import(new Commodity('2710 99 000 00', 10))), '188.1', 1000);
    }

    public function testGasSoldForVehiclesIsTaxedOnSupply(): void
    {
        $sale = $this->sell($this->shop, new Commodity('2711 21', 5));

        $this->assertLevied($this->excise($sale), '188.1', 1000);
    }

    /* ── Call termination (188.3) ── */

    public function testMobileCallTermination(): void
    {
        $this->assertLevied($this->excise($this->sell(Party::company('telco'), new Commodity(Calls::MOBILE, 10_000))), '188.3', 1500);
        $this->assertLevied($this->excise($this->sell(Party::company('telco'), new Commodity(Calls::FIXED, 10_000))), '188.3', 800);
    }

    /* ── Refund and the law ── */

    public function testRefundReversesTheDuty(): void
    {
        $sale = $this->sell($this->producer, new Commodity('2208 30', 10, alcoholPercent: 40));
        $duty = $this->excise(new Refund($sale, $this->today));

        $this->assertSame(-9000, $duty->amount->tetri);
    }

    public function testEveryRateRuleCitesAnArticle(): void
    {
        $root = dirname(__DIR__) . '/src/Excise/Rate';
        $checked = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $reflection = new ReflectionClass('GeorgianTax\\Excise\\Rate\\' . $file->getBasename('.php'));

            if ($reflection->isInterface()) {
                continue;
            }

            $this->assertNotEmpty($reflection->getAttributes(Article::class), "{$reflection->name} has no #[Article].");
            $checked++;
        }

        $this->assertSame(6, $checked);
    }

    /* ── Helpers ── */

    private function import(Commodity $c, int $customs = 10_000, ?ImportRelief $relief = null): Import
    {
        return new Import($this->importer, Money::gel($customs), $this->today, commodity: $c, relief: $relief);
    }

    private function sell(Party $seller, Commodity $c): Pay
    {
        return new Pay($this->shop === $seller ? Party::person('customer') : $this->shop, $seller, K::GoodsSupply, Money::gel(1000), $this->today, commodity: $c);
    }

    private function car(?int $engineCc = null, ?int $yearMade = null, bool $hybrid = false, bool $electric = false,
        bool $rightHandDrive = false, bool $sportsCar = false, bool $classicCar = false): Import
    {
        return $this->import(new Commodity('8703 23', 1, engineCc: $engineCc, yearMade: $yearMade, hybrid: $hybrid,
            electric: $electric, rightHandDrive: $rightHandDrive, sportsCar: $sportsCar, classicCar: $classicCar));
    }

    private function excise(object $event): Outcome
    {
        return $this->engine->process($event)->for('excise');
    }

    private function assertLevied(Outcome $outcome, string $article, int|float $gel): void
    {
        $this->assertInstanceOf(Levied::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
        $this->assertSame((int) round($gel * 100), $outcome->amount->tetri, (string) $outcome);
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
