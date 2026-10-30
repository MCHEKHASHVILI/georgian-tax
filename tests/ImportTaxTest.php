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
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\Engine\Outcome\Levied;
use GeorgianTax\Engine\Outcome\NotTaxable;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\Outcome\Unresolved;
use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\Excise\Excise;
use GeorgianTax\ImportTax\ImportTax;
use GeorgianTax\Law\Article;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Rates\ConfigRateTable;
use GeorgianTax\Vat\Vat;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** One test per row of the import tax section of docs/CATALOGUE.md. */
final class ImportTaxTest extends TestCase
{
    private TaxEngine $engine;
    private DateTimeImmutable $today;
    private Party $importer;

    protected function setUp(): void
    {
        $ledger = new InMemoryLedger();
        $this->engine = new TaxEngine(
            $ledger,
            ImportTax::standard(),
            Excise::standard(),
            Vat::standard($ledger, ConfigRateTable::standard()),
        );
        $this->today = new DateTimeImmutable('2026-10-30');
        $this->importer = Party::company('importer', Status::VatPayer);
    }

    /* ── Who pays, on what (195–197) ── */

    public function testOnlyImportsCarryImportTax(): void
    {
        $this->assertNotTaxable($this->tax(new Pay($this->importer, Party::company('shop'), K::GoodsSupply, Money::gel(100), $this->today)), '195–196');
    }

    public function testImportWithoutHsCodeIsUnresolved(): void
    {
        $this->assertInstanceOf(Unresolved::class, $this->tax(new Import($this->importer, Money::gel(1000), $this->today)));
    }

    public function testUnlistedGoodsAreAtZero(): void
    {
        $this->assertNotTaxable($this->tax($this->import(new Commodity('8471 30'))), '197');
    }

    public function testListedMeatAt12Percent(): void
    {
        $tax = $this->tax($this->import(new Commodity('0201 30'), 10_000));

        $this->assertTaxed($tax, '197.1', 1200);
        $this->assertSame(Collection::AtCustoms, $tax->collection);
        $this->assertSame('importer', $tax->taxpayer->id);
    }

    public function testWholeChapter19At12Percent(): void
    {
        $this->assertTaxed($this->tax($this->import(new Commodity('1905 31'), 1000)), '197.1', 120);
    }

    public function testMilkOnlyInSmallPackagesAt12Percent(): void
    {
        $this->assertTaxed($this->tax($this->import(new Commodity('0401 10 100 00'), 1000)), '197.1', 120);
        $this->assertNotTaxable($this->tax($this->import(new Commodity('0401 10 900 00'), 1000)), '197');
    }

    public function testCheeseAt5Percent(): void
    {
        $this->assertTaxed($this->tax($this->import(new Commodity('0406 90'), 10_000)), '197.2', 500);
    }

    public function testImportTaxJoinsTheVatBase(): void
    {
        $report = $this->engine->process($this->import(new Commodity('0201 30'), 10_000));
        $vat = $report->for('vat');

        $this->assertInstanceOf(Taxable::class, $vat);
        $this->assertSame(11_200 * 18, $vat->amount->tetri);
    }

    /* ── Cars (197.6) ── */

    public function testCarPerCcPlus5PercentPerYear(): void
    {
        // 2,000 cm³ × 0.05 = 100, plus 5% × 7 years = 35
        $this->assertLevied($this->tax($this->import(new Commodity('8703 23', engineCc: 2000, yearMade: 2019))), '197.6', 135);
    }

    public function testNewCarPaysOnlyThePerCcPart(): void
    {
        $this->assertLevied($this->tax($this->import(new Commodity('8703 23', engineCc: 1600, yearMade: 2026))), '197.6', 80);
    }

    public function testCarWithoutYearIsUnresolved(): void
    {
        $this->assertInstanceOf(Unresolved::class, $this->tax($this->import(new Commodity('8703 23', engineCc: 2000))));
    }

    public function testCarImportPaysImportTaxExciseAndVat(): void
    {
        $report = $this->engine->process($this->import(new Commodity('8703 23', engineCc: 2000, yearMade: 2019), 20_000));

        $this->assertSame(13_500, $report->for('import')->amount->tetri);
        $this->assertSame(900_000, $report->for('excise')->amount->tetri);
    }

    /* ── Alcohol in euro (197.3) ── */

    public function testWineInSmallBottlesPerLitre(): void
    {
        // 100 l × €0.50 × 3.00 GEL
        $this->assertLevied($this->tax($this->import(new Commodity('2204 21', 100, alcoholPercent: 13), eur: 3.0)), '197.3', 150);
    }

    public function testSparklingWine(): void
    {
        $this->assertLevied($this->tax($this->import(new Commodity('2204 10', 100, alcoholPercent: 12), eur: 3.0)), '197.3', 450);
    }

    public function testSpiritsPerDegreePerHectolitre(): void
    {
        // 200 l at 40% = 2 hl × 40 = 80 %vol·hl × €1.50 × 3.00 GEL
        $this->assertLevied($this->tax($this->import(new Commodity('2208 30', 200, alcoholPercent: 40), eur: 3.0)), '197.3', 360);
    }

    public function testAlcoholWithoutEuroRateIsUnresolved(): void
    {
        $this->assertInstanceOf(Unresolved::class, $this->tax($this->import(new Commodity('2208 30', 200, alcoholPercent: 40))));
    }

    /* ── Exemptions (199) ── */

    public function testElectricCarIsExempt(): void
    {
        $this->assertExempt($this->tax($this->import(new Commodity('8703 80', electric: true))), '199.ჟ');
    }

    public function testUndenaturedAlcoholOver80IsExemptByCode(): void
    {
        $this->assertExempt($this->tax($this->import(new Commodity('2207 10 000 00', 1000, alcoholPercent: 96), eur: 3.0)), '199.პ');
    }

    public function testSaltIsExemptButNotTalc2526_20(): void
    {
        $this->assertExempt($this->tax($this->import(new Commodity('2501 00 310 00'))), '199.პ');
        $this->assertExempt($this->tax($this->import(new Commodity('2526 10'))), '199.პ');
        // 2526 20 is carved out of the exemption, and chapter 25 is on the 12% list
        $this->assertTaxed($this->tax($this->import(new Commodity('2526 20 000 00'), 1000)), '197.1', 120);
    }

    public function testHumanitarianAidIsExempt(): void
    {
        $this->assertExempt($this->tax($this->import(new Commodity('0201 30'), 50_000, ImportRelief::Humanitarian)), '199.ა');
    }

    public function testPostalReliefCoversChapters28To97UpTo300(): void
    {
        $this->assertExempt($this->tax($this->import(new Commodity('3926 10 000 00'), 250, ImportRelief::PostalSmall)), '199.დ.ვ');
        $this->assertTaxed($this->tax($this->import(new Commodity('3926 10 000 00'), 400, ImportRelief::PostalSmall)), '197.2', 20);
    }

    public function testTravellerFoodReliefCoversFoodChaptersOnly(): void
    {
        $this->assertExempt($this->tax($this->import(new Commodity('0201 30'), 400, ImportRelief::TravellerFood)), '199.დ.ა');
        $this->assertTaxed($this->tax($this->import(new Commodity('3926 10 000 00'), 400, ImportRelief::TravellerFood)), '197.2', 20);
    }

    public function testReturningResidentReliefExcludesVehicles(): void
    {
        $car = new Commodity('8703 23', engineCc: 2000, yearMade: 2026);

        $this->assertLevied($this->tax($this->import($car, 10_000, ImportRelief::ReturningResident)), '197.6', 100);
        $this->assertExempt($this->tax($this->import(new Commodity('3926 10 000 00'), 10_000, ImportRelief::ReturningResident)), '199.დ.დ');
    }

    public function testRawTobaccoReliefEndsIn2028(): void
    {
        $leaf = new Commodity('2401 10', 1000);
        $later = new Import($this->importer, Money::gel(1000), new DateTimeImmutable('2028-03-01'), commodity: $leaf, relief: ImportRelief::RawTobacco);

        $this->assertExempt($this->tax($this->import($leaf, 1000, ImportRelief::RawTobacco)), '199.ი');
        $this->assertTaxed($this->tax($later), '197.1', 120);
    }

    /* ── Refund and the law ── */

    public function testRefundReversesTheTax(): void
    {
        $import = $this->import(new Commodity('0201 30'), 10_000);

        $this->assertSame(-120_000, $this->tax(new Refund($import, $this->today))->amount->tetri);
    }

    public function testEveryRuleAndStageCitesAnArticle(): void
    {
        $checked = 0;

        foreach (['Rule\PassengerCar', 'Rule\Alcohol', 'Rule\ListedRate', 'Stage\IdentifyImport', 'Stage\ApplyExemptions'] as $class) {
            $this->assertNotEmpty((new ReflectionClass("GeorgianTax\\ImportTax\\$class"))->getAttributes(Article::class), $class);
            $checked++;
        }

        $this->assertSame(5, $checked);
    }

    /* ── Helpers ── */

    private function import(Commodity $c, int $customs = 10_000, ?ImportRelief $relief = null, ?float $eur = null): Import
    {
        return new Import($this->importer, Money::gel($customs), $this->today, commodity: $c, relief: $relief, eurToGel: $eur);
    }

    private function tax(object $event): Outcome
    {
        return $this->engine->process($event)->for('import');
    }

    private function assertTaxed(Outcome $outcome, string $article, int $gel): void
    {
        $this->assertInstanceOf(Taxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
        $this->assertSame($gel * 100, $outcome->amount->tetri, (string) $outcome);
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
