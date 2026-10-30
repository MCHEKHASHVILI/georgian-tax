<?php

declare(strict_types=1);

namespace GeorgianTax\Tests;

use DateTimeImmutable;
use GeorgianTax\Domain\Event\OwnProperty;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Domain\Property\PropertyExemption as X;
use GeorgianTax\Domain\Property\PropertyItem;
use GeorgianTax\Domain\Property\PropertyKind as K;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\Engine\Outcome\Levied;
use GeorgianTax\Engine\Outcome\NotTaxable;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\Outcome\Unresolved;
use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\Excise\Excise;
use GeorgianTax\ImportTax\ImportTax;
use GeorgianTax\IncomeTax\IncomeTax;
use GeorgianTax\Law\Article;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Ledger\StaticBooks;
use GeorgianTax\ProfitTax\ProfitTax;
use GeorgianTax\PropertyTax\MunicipalRates;
use GeorgianTax\PropertyTax\PropertyTax;
use GeorgianTax\Rates\ConfigRateTable;
use GeorgianTax\Vat\Vat;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** One test per row of the property tax section of docs/CATALOGUE.md — run through all six taxes. */
final class PropertyTaxTest extends TestCase
{
    private TaxEngine $engine;
    private Party $company;
    private Party $person;

    protected function setUp(): void
    {
        $this->engine = self::engine();
        $this->company = Party::company('acme');
        $this->person = Party::person('nino');
    }

    private static function engine(?MunicipalRates $rates = null): TaxEngine
    {
        $ledger = new InMemoryLedger();
        $table = ConfigRateTable::standard();

        return new TaxEngine(
            $ledger,
            ImportTax::standard(),
            Excise::standard(),
            Vat::standard($ledger, $table),
            IncomeTax::standard($ledger, $table),
            ProfitTax::standard($ledger, $table, new StaticBooks()),
            PropertyTax::standard($rates),
        );
    }

    /* ── Who pays, on what (201, 203) ── */

    public function testOnlyHoldingsCarryPropertyTax(): void
    {
        $sale = new Pay($this->company, $this->person, IncomeKind::GoodsSupply, Money::gel(100), new DateTimeImmutable('2026-10-30'));

        $this->assertNotTaxable($this->property($sale), '201, 203');
    }

    public function testHoldingIsIgnoredByEveryOtherTax(): void
    {
        $report = $this->engine->process($this->own($this->company, new PropertyItem(K::Equipment, Money::gel(100_000))));

        foreach (['import', 'excise', 'vat', 'income', 'profit'] as $tax) {
            $this->assertSame(0, $report->amountOf($tax)->tetri, $tax);
        }

        $this->assertSame(100_000, $report->totalTax()->tetri);
    }

    public function testStatePaysNone(): void
    {
        $this->assertNotTaxable($this->property($this->own(Party::state(), new PropertyItem(K::Building, Money::gel(1_000_000)))), '201, 203');
    }

    public function testIndividualEquipmentOutsideBusinessIsNotTaxable(): void
    {
        $this->assertNotTaxable($this->property($this->own($this->person, new PropertyItem(K::Equipment, Money::gel(50_000)), income: 200_000)), '201.1.გ');
    }

    /* ── Company property (202.1–3¹) ── */

    public function testCompanyEquipmentAt1Percent(): void
    {
        $tax = $this->property($this->own($this->company, new PropertyItem(K::Equipment, Money::gel(100_000))));

        $this->assertTaxed($tax, '202.1', 1000);
        $this->assertSame('2027-04-01', $tax->due->format('Y-m-d'));
    }

    public function testOldRealEstateIsRevaluedThreeTimes(): void
    {
        $building = new PropertyItem(K::Building, Money::gel(100_000), acquiredYear: 1998);

        $this->assertTaxed($this->property($this->own($this->company, $building)), '202.1', 3000);
    }

    public function testRealEstateFrom2000To2003Twice(): void
    {
        $this->assertTaxed($this->property($this->own($this->company, new PropertyItem(K::Building, Money::gel(100_000), acquiredYear: 2002))), '202.1', 2000);
    }

    public function testRealEstateFrom2004OneAndAHalf(): void
    {
        $this->assertTaxed($this->property($this->own($this->company, new PropertyItem(K::Building, Money::gel(100_000), acquiredYear: 2004))), '202.1', 1500);
    }

    public function testRealEstateOfUnknownDateThreeTimes(): void
    {
        $this->assertTaxed($this->property($this->own($this->company, new PropertyItem(K::Building, Money::gel(100_000)))), '202.1', 3000);
    }

    public function testAuditedRevaluationSkipsTheMultiplier(): void
    {
        $building = new PropertyItem(K::Building, Money::gel(100_000), acquiredYear: 1998, revaluedWithAudit: true);

        $this->assertTaxed($this->property($this->own($this->company, $building)), '202.1', 1000);
    }

    public function testLeasingCompanyAt06Percent(): void
    {
        $car = new PropertyItem(K::PassengerCar, Money::gel(100_000), leasedOut: true, byLeasingCompany: true);

        $this->assertTaxed($this->property($this->own($this->company, $car)), '202.3¹', 600);
    }

    public function testCompanyExistingPartOfTheYearPaysProportionally(): void
    {
        $tax = $this->property($this->own($this->company, new PropertyItem(K::Equipment, Money::gel(120_000)), months: 6));

        $this->assertTaxed($tax, '202.1', 600);
    }

    public function testCouncilMayChargeLess(): void
    {
        $engine = self::engine(new MunicipalRates(companyPercent: 0.5));
        $tax = $engine->process($this->own($this->company, new PropertyItem(K::Equipment, Money::gel(100_000))))->for('property');

        $this->assertTaxed($tax, '202.1', 500);
    }

    public function testCouncilCannotExceedTheCaps(): void
    {
        foreach ([['companyPercent' => 1.2], ['individualPercent' => 0.5], ['landMultipliers' => ['gori' => 2.0]]] as $decision) {
            try {
                new MunicipalRates(...$decision);
                $this->fail('Accepted ' . json_encode($decision));
            } catch (InvalidArgumentException $e) {
                $this->assertInstanceOf(InvalidArgumentException::class, $e);
            }
        }
    }

    /* ── Individual property (202.5, 206.1.ა) ── */

    public function testIndividualWithFamilyIncomeUpTo100kIsExempt(): void
    {
        $this->assertExempt($this->property($this->own($this->person, new PropertyItem(K::Building, Money::gel(500_000)), income: 100_000)), '206.1.ა');
    }

    public function testIndividualAbove100kPaysOnMarketValue(): void
    {
        $tax = $this->property($this->own($this->person, new PropertyItem(K::Building, Money::gel(500_000)), income: 150_000));

        $this->assertTaxed($tax, '202.5', 5000);
        $this->assertSame('2027-11-15', $tax->due->format('Y-m-d'));
    }

    public function testIndividualOwningPartOfTheYear(): void
    {
        $this->assertTaxed($this->property($this->own($this->person, new PropertyItem(K::PassengerCar, Money::gel(60_000)), months: 4, income: 150_000)), '202.5', 200);
    }

    public function testIndividualAt08PercentWhereTheCouncilSaysSo(): void
    {
        $engine = self::engine(new MunicipalRates(individualPercent: 0.8));
        $tax = $engine->process($this->own($this->person, new PropertyItem(K::Building, Money::gel(500_000)), income: 150_000))->for('property');

        $this->assertTaxed($tax, '202.5', 4000);
    }

    public function testIndividualWithoutFamilyIncomeIsUnresolved(): void
    {
        $this->assertInstanceOf(Unresolved::class, $this->property($this->own($this->person, new PropertyItem(K::Building, Money::gel(500_000)))));
    }

    /* ── Land (204) ── */

    public function testArableLandInMarneuli(): void
    {
        $tax = $this->property($this->own($this->person, PropertyItem::land(K::Arable, 2.5, 'Marneuli')));

        $this->assertLevied($tax, '204.1.ა', 250);
        $this->assertSame('2026-11-15', $tax->due->format('Y-m-d'));
    }

    public function testLandIsTaxedWhateverTheFamilyIncome(): void
    {
        $this->assertLevied($this->property($this->own($this->person, PropertyItem::land(K::Arable, 1, 'Kutaisi'), income: 10_000)), '204.1.ა', 71);
    }

    public function testTbilisiVillageRate(): void
    {
        $this->assertLevied($this->property($this->own($this->person, PropertyItem::land(K::Homestead, 1, 'tbilisi:Kojori'))), '204.1.ა', 95);
    }

    public function testHayfieldAndPasture(): void
    {
        $this->assertLevied($this->property($this->own($this->person, PropertyItem::land(K::Hayfield, 10, 'Dedoplistskaro'))), '204.1.ბ', 200);
        $this->assertLevied($this->property($this->own($this->person, PropertyItem::land(K::Pasture, 10, 'Dedoplistskaro'))), '204.1.ბ', 70);
    }

    public function testCouncilMultiplierOnAgriculturalLand(): void
    {
        $engine = self::engine(new MunicipalRates(landMultipliers: ['marneuli' => 1.5]));
        $tax = $engine->process($this->own($this->person, PropertyItem::land(K::Arable, 1, 'Marneuli')))->for('property');

        $this->assertLevied($tax, '204.1.ა', 150);
    }

    public function testUnknownMunicipalityIsUnresolved(): void
    {
        $this->assertInstanceOf(Unresolved::class, $this->property($this->own($this->person, PropertyItem::land(K::Pasture, 1, 'Atlantis'))));
    }

    public function testNonAgriculturalLandPerSquareMetre(): void
    {
        $this->assertLevied($this->property($this->own($this->company, PropertyItem::land(K::NonAgriculturalLand, 1000, 'Tbilisi'))), '204.2', 240);
    }

    public function testTerritorialCoefficient(): void
    {
        $engine = self::engine(new MunicipalRates(territorialCoefficients: ['tbilisi' => 1.5]));
        $tax = $engine->process($this->own($this->company, PropertyItem::land(K::NonAgriculturalLand, 1000, 'Tbilisi')))->for('property');

        $this->assertLevied($tax, '204.2', 360);
    }

    public function testNaturalResourceLand(): void
    {
        $this->assertLevied($this->property($this->own($this->company, PropertyItem::land(K::NaturalResourceLand, 50))), '204.3', 150);
    }

    /* ── Exemptions (206) ── */

    public function testOrganizationPropertyOutsideBusinessIsExempt(): void
    {
        $charity = Party::company('fund')->withKind(PartyKind::Organization);

        $this->assertExempt($this->property($this->own($charity, new PropertyItem(K::Building, Money::gel(1_000_000)))), '206.1.ე');
        $this->assertTaxed($this->property($this->own($charity, new PropertyItem(K::Equipment, Money::gel(100_000), usedInBusiness: true))), '202.1', 1000);
    }

    public function testFreeIndustrialZoneIsExempt(): void
    {
        $this->assertExempt($this->property($this->own(Party::company('fiz', Status::FreeIndustrialZone), new PropertyItem(K::Equipment, Money::gel(100_000)))), '206.1.ჩ');
    }

    public function testYachtsAreExempt(): void
    {
        $this->assertExempt($this->property($this->own($this->person, new PropertyItem(K::Yacht, Money::gel(300_000)), income: 500_000)), '206.1.ჰ3');
    }

    public function testBankruptIsExempt(): void
    {
        $this->assertExempt($this->property($this->own(Party::company('bust', Status::Bankrupt), new PropertyItem(K::Equipment, Money::gel(100_000)))), '206.1.ჰ4');
    }

    public function testAssertedExemption(): void
    {
        $this->assertExempt($this->property($this->own($this->company, new PropertyItem(K::Equipment, Money::gel(100_000), exemption: X::MedicalProperty))), '206.1.წ');
    }

    public function testExpiredExemptionNoLongerApplies(): void
    {
        $hotel = new PropertyItem(K::Building, Money::gel(100_000), acquiredYear: 2020, exemption: X::TouristZoneHotel);

        $this->assertTaxed($this->property($this->own($this->company, $hotel)), '202.1', 1000);
    }

    public function testLeasedOutLandLosesItsExemption(): void
    {
        $plot = PropertyItem::land(K::Arable, 3, 'Gori', X::SmallFarmOwnedSince2004);
        $leased = PropertyItem::land(K::Arable, 3, 'Gori', X::SmallFarmOwnedSince2004, leasedOut: true);

        $this->assertExempt($this->property($this->own($this->person, $plot)), '206.1.ტ');
        $this->assertLevied($this->property($this->own($this->person, $leased)), '204.1.ა', 246);
    }

    public function testStateUniversityKeepsItsExemptionWhenLeasing(): void
    {
        $building = new PropertyItem(K::Building, Money::gel(1_000_000), leasedOut: true, exemption: X::StateUniversity);

        $this->assertExempt($this->property($this->own(Party::company('tsu'), $building)), '206.1.ჰ⁵');
    }

    public function testEveryRuleAndStageCitesAnArticle(): void
    {
        foreach (['Rule\AgriculturalLand', 'Rule\OtherLand', 'Rule\CompanyProperty', 'Rule\IndividualProperty', 'Stage\IdentifyTaxpayer', 'Stage\ApplyExemptions'] as $class) {
            $this->assertNotEmpty((new ReflectionClass("GeorgianTax\\PropertyTax\\$class"))->getAttributes(Article::class), $class);
        }
    }

    /* ── Helpers ── */

    private function own(Party $owner, PropertyItem $item, int $months = 12, ?int $income = null): OwnProperty
    {
        return new OwnProperty($owner, $item, 2026, $months, $income === null ? null : Money::gel($income));
    }

    private function property(object $event): Outcome
    {
        return $this->engine->process($event)->for('property');
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
