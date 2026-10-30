<?php

declare(strict_types=1);

namespace GeorgianTax\Tests;

use DateTimeImmutable;
use GeorgianTax\Domain\Asset\Asset;
use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\SelfSupply;
use GeorgianTax\Domain\Event\SelfSupplyReason;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Income\SupplyCategory as C;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Residency;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\Engine\Outcome\ExemptWithCredit;
use GeorgianTax\Engine\Outcome\NotTaxable;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\Engine\TaxReport;
use GeorgianTax\IncomeTax\IncomeTax;
use GeorgianTax\Law\Article;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Rates\ConfigRateTable;
use GeorgianTax\Vat\Vat;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/** One test per row of the VAT section of docs/CATALOGUE.md. */
final class VatTest extends TestCase
{
    private InMemoryLedger $ledger;
    private TaxEngine $engine;
    private DateTimeImmutable $today;
    private Party $seller;
    private Party $buyer;

    protected function setUp(): void
    {
        $this->ledger = new InMemoryLedger();
        $rates = ConfigRateTable::standard();
        $this->engine = new TaxEngine($this->ledger, IncomeTax::standard($this->ledger, $rates), Vat::standard($this->ledger, $rates));
        $this->today = new DateTimeImmutable('2026-10-30');
        $this->seller = Party::company('seller', Status::VatPayer);
        $this->buyer = Party::company('buyer', Status::VatPayer);
    }

    /* ── Registered suppliers ── */

    public function testRegisteredSupplierCharges18Percent(): void
    {
        $report = $this->process($this->pay($this->buyer, $this->seller, K::GoodsSupply, 1000));
        $vat = $report->for('vat');

        $this->assertCharged($vat, '168.1', Collection::MonthlyDeclaration, 180);
        $this->assertSame('buyer', $vat->creditableBy?->id);
        $this->assertSame(18000, $report->vatCharged()->tetri);
    }

    public function testPriceIncludingVatIsSplitOut(): void
    {
        $event = new Pay($this->buyer, $this->seller, K::ServiceFee, Money::gel(1180), $this->today, pricesIncludeVat: true);

        $this->assertCharged($this->vat($event), '168.1', Collection::MonthlyDeclaration, 180);
    }

    public function testConsumerCannotCredit(): void
    {
        $vat = $this->vat($this->pay(Party::person('nino'), $this->seller, K::GoodsSupply, 100));

        $this->assertSame(null, $vat->creditableBy);
    }

    /* ── Registration threshold ── */

    public function testUnregisteredSupplierBelowThresholdChargesNothingButCounts(): void
    {
        $shop = Party::company('shop');

        $this->assertNotTaxable($this->process($this->pay($this->buyer, $shop, K::GoodsSupply, 60_000))->for('vat'), '165.1');
        $this->assertSame(6_000_000, $this->ledger->turnover($shop, 'vat', $this->today->modify('-1 year'))->tetri);
    }

    public function testSupplyCrossing100kIsTaxedFromThatSupply(): void
    {
        $shop = Party::company('shop');
        $this->ledger->seedTurnover($shop, 'vat', new DateTimeImmutable('2026-03-01'), Money::gel(98_000));

        $this->assertCharged($this->vat($this->pay($this->buyer, $shop, K::GoodsSupply, 5000)), '165.1', Collection::MonthlyDeclaration, 900);
    }

    public function testTurnoverOlderThan12MonthsDoesNotCount(): void
    {
        $shop = Party::company('shop');
        $this->ledger->seedTurnover($shop, 'vat', new DateTimeImmutable('2025-06-01'), Money::gel(98_000));

        $this->assertNotTaxable($this->vat($this->pay($this->buyer, $shop, K::GoodsSupply, 5000)), '165.1');
    }

    public function testFixedEstablishmentOwesFromFirstSupply(): void
    {
        $branch = new Party('branch', PartyKind::Company, Residency::NonResident, 'DE', hasGeorgianPE: true);

        $this->assertCharged($this->vat($this->pay($this->buyer, $branch, K::ServiceFee, 1000)), '165.9', Collection::MonthlyDeclaration, 180);
    }

    /* ── Cross-border services ── */

    public function testForeignServiceToGeorgianBusinessIsReverseCharged(): void
    {
        $client = Party::company('acme');
        $event = new Pay($client, Party::foreignCompany('gmbh', 'DE'), K::ServiceFee, Money::gel(1000), $this->today, Place::abroad('DE'));
        $vat = $this->vat($event);

        $this->assertCharged($vat, '161.1.ა', Collection::ReverseCharge, 180);
        $this->assertSame('acme', $vat->agent?->id);
        $this->assertSame(null, $vat->creditableBy); // acme is not VAT-registered
    }

    public function testRegisteredCustomerCreditsItsReverseCharge(): void
    {
        $event = new Pay($this->buyer, Party::foreignCompany('gmbh', 'DE'), K::ServiceFee, Money::gel(1000), $this->today, Place::abroad('DE'));

        $this->assertSame('buyer', $this->vat($event)->creditableBy?->id);
    }

    public function testForeignElectronicServiceToGeorgianConsumerIsOnTheSupplier(): void
    {
        $event = new Pay(Party::person('nino'), Party::foreignCompany('streaming', 'US'), K::ServiceFee, Money::gel(30), $this->today, Place::abroad('US'), supply: C::ElectronicService);

        $this->assertCharged($this->vat($event), '165².2.ე', Collection::MonthlyDeclaration);
    }

    public function testServiceToAForeignBusinessIsSuppliedAbroad(): void
    {
        $this->assertNotTaxable($this->vat($this->pay(Party::foreignCompany('gmbh', 'DE'), $this->seller, K::ServiceFee, 1000)), '162¹.3.ა');
    }

    public function testConsultingForAForeignConsumerIsSuppliedAbroad(): void
    {
        $event = new Pay(Party::foreigner('john', 'US'), $this->seller, K::ServiceFee, Money::gel(1000), $this->today, supply: C::Consulting);

        $this->assertNotTaxable($this->vat($event), '162¹.14');
    }

    public function testRestaurantMealForATouristIsTaxedWhereServed(): void
    {
        $event = new Pay(Party::foreigner('john', 'US'), $this->seller, K::ServiceFee, Money::gel(100), $this->today, supply: C::Restaurant);

        $this->assertCharged($this->vat($event), '168.1', Collection::MonthlyDeclaration, 18);
    }

    /* ── Goods leaving and entering ── */

    public function testExportIsExemptWithCreditAndCounts(): void
    {
        $shop = Party::company('exporter');
        $event = new Pay(Party::foreignCompany('importer', 'AM'), $shop, K::GoodsSupply, Money::gel(50_000), $this->today, Place::exportTo('AM'));

        $this->assertExemptWithCredit($this->process($event)->for('vat'), '172.4.ე');
        $this->assertSame(5_000_000, $this->ledger->turnover($shop, 'vat', $this->today->modify('-1 year'))->tetri);
    }

    public function testImportPaysAtCustomsOnValuePlusDuties(): void
    {
        $import = new Import($this->buyer, Money::gel(10_000), $this->today, dutiesAndFees: Money::gel(1000));
        $vat = $this->vat($import);

        $this->assertCharged($vat, '165².2.ა', Collection::AtCustoms, 1980);
        $this->assertSame('buyer', $vat->creditableBy?->id);
    }

    public function testListedImportIsDueIn45Days(): void
    {
        $vat = $this->vat(new Import($this->buyer, Money::gel(10_000), $this->today, onDeferredPaymentList: true));

        $this->assertSame('2026-12-14', $vat->due->format('Y-m-d'));
    }

    public function testPassengerCarImportIsExempt(): void
    {
        $this->assertExempt($this->vat(new Import(Party::person('nino'), Money::gel(15_000), $this->today, goods: C::PassengerCarOrMotorcycle)), '173.ზ');
    }

    /* ── Exemptions ── */

    public function testMedicalCareIsExemptWithoutCredit(): void
    {
        $this->assertExempt($this->vat(new Pay(Party::person('nino'), Party::company('clinic'), K::ServiceFee, Money::gel(200), $this->today, supply: C::MedicalCare)), '170.1.ა–გ');
    }

    public function testInterestIsAFinancialServiceExemptWithoutCredit(): void
    {
        $this->assertExempt($this->vat($this->pay(Party::person('nino'), Party::company('bank', Status::VatPayer), K::Interest, 100)), '171.1.ა');
    }

    public function testSellingLandIsExempt(): void
    {
        $land = new Asset(AssetType::Land, new DateTimeImmutable('2020-01-01'), Money::gel(50_000));

        $this->assertExempt($this->vat(new Sell($this->seller, $this->buyer, $land, Money::gel(80_000), $this->today)), '171.1.გ');
    }

    public function testSupplyToADiplomaticMissionIsExemptWithCredit(): void
    {
        $embassy = Party::company('embassy', Status::DiplomaticMission);

        $this->assertExemptWithCredit($this->vat($this->pay($embassy, $this->seller, K::GoodsSupply, 1000)), '172.3');
    }

    public function testPrimaryAgriculturalProduceIsExemptWithCredit(): void
    {
        $event = new Pay($this->buyer, $this->seller, K::GoodsSupply, Money::gel(1000), $this->today, supply: C::PrimaryAgriculturalProduce);

        $this->assertExemptWithCredit($this->vat($event), '172.4.უ');
    }

    /* ── Not a supply ── */

    public function testSalaryIsNotASupply(): void
    {
        $this->assertNotTaxable($this->vat($this->pay($this->seller, Party::person('nino'), K::Salary, 3000)), '159');
    }

    public function testIndividualSellingTheirCarIsNotEconomicActivity(): void
    {
        $car = new Asset(AssetType::Car, new DateTimeImmutable('2024-01-01'), Money::gel(20_000));

        $this->assertNotTaxable($this->vat(new Sell(Party::person('nino'), $this->buyer, $car, Money::gel(18_000), $this->today)), '158.2.ბ');
    }

    public function testSellingANonResidentialBuildingIsAlwaysEconomicActivity(): void
    {
        $office = new Asset(AssetType::RealEstate, new DateTimeImmutable('2024-01-01'), Money::gel(300_000));
        $vat = $this->vat(new Sell(Party::person('nino'), $this->buyer, $office, Money::gel(500_000), $this->today));

        $this->assertCharged($vat, '165.1', Collection::MonthlyDeclaration, 90_000);
    }

    /* ── Deemed supplies ── */

    public function testGiftByAVatPayerIsADeemedSupply(): void
    {
        $this->assertCharged($this->vat(new Gift($this->seller, Party::person('nino'), Money::gel(500), $this->today)), '168.1', Collection::MonthlyDeclaration, 90);
    }

    public function testSmallGiftIsNotASupply(): void
    {
        $this->assertNotTaxable($this->vat(new Gift($this->seller, Party::person('nino'), Money::gel(40), $this->today)), '157.ი');
    }

    public function testFreeTransferToTheStateIsExemptWithCredit(): void
    {
        $this->assertExemptWithCredit($this->vat(new Gift($this->seller, Party::state(), Money::gel(5000), $this->today)), '172.4.გ');
    }

    public function testStockLeftOnDeregistrationIsTaxed(): void
    {
        $event = new SelfSupply($this->seller, Money::gel(10_000), $this->today, SelfSupplyReason::Deregistration);

        $this->assertCharged($this->vat($event), '168.1', Collection::MonthlyDeclaration, 1800);
    }

    public function testMarginSchemeTaxesTheMarginWithoutCredit(): void
    {
        $dealer = Party::company('dealer', Status::VatPayer, Status::TaxableDealer);
        $usedCar = new Asset(AssetType::Other, new DateTimeImmutable('2026-09-01'), Money::gel(10_000));
        $vat = $this->vat(new Sell($dealer, $this->buyer, $usedCar, Money::gel(11_180), $this->today, marginScheme: true));

        $this->assertCharged($vat, '161²', Collection::MonthlyDeclaration, 180);
        $this->assertSame(null, $vat->creditableBy);
    }

    /* ── Engine, refunds, traceability ── */

    public function testOneEventRunsThroughBothTaxes(): void
    {
        $report = $this->process($this->pay($this->seller, Party::person('nino'), K::ServiceFee, 1000));

        $this->assertInstanceOf(Taxable::class, $report->for('income'));        // 154.1.დ withheld
        $this->assertNotTaxable($report->for('vat'), '165.1');                 // unregistered, below threshold
    }

    public function testRefundReversesVat(): void
    {
        $sale = $this->pay($this->buyer, $this->seller, K::GoodsSupply, 1000);
        $vat = $this->vat(new Refund($sale, $this->today));

        $this->assertSame(-18000, $vat->amount->tetri);
    }

    public function testEveryVatRuleAndPlaceRuleCitesAnArticle(): void
    {
        $root = dirname(__DIR__) . '/src/Vat';
        $checked = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->getExtension() !== 'php' || !preg_match('#/(Rule|Place)/#', $file->getPathname())) {
                continue;
            }

            $class = 'GeorgianTax\\Vat\\' . str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($root) + 1));
            $reflection = new ReflectionClass($class);

            if ($reflection->isInterface()) {
                continue;
            }

            $this->assertNotEmpty($reflection->getAttributes(Article::class), "$class has no #[Article].");
            $checked++;
        }

        $this->assertGreaterThan(10, $checked);
    }

    /* ── Helpers ── */

    private function pay(Party $customer, Party $supplier, K $kind, int $gel): Pay
    {
        return new Pay($customer, $supplier, $kind, Money::gel($gel), $this->today);
    }

    private function process(TaxableEvent $event): TaxReport
    {
        return $this->engine->process($event);
    }

    private function vat(TaxableEvent $event): Outcome
    {
        return $this->process($event)->for('vat');
    }

    private function assertCharged(Outcome $outcome, string $article, Collection $collection, ?int $vatGel = null): void
    {
        $this->assertInstanceOf(Taxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
        $this->assertSame('18%', (string) $outcome->rate, (string) $outcome);
        $this->assertSame($collection, $outcome->collection, (string) $outcome);

        if ($vatGel !== null) {
            $this->assertSame($vatGel * 100, $outcome->amount->tetri, (string) $outcome);
        }
    }

    private function assertExempt(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(Exempt::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }

    private function assertExemptWithCredit(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(ExemptWithCredit::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }

    private function assertNotTaxable(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(NotTaxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }
}
