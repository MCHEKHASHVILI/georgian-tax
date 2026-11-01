<?php

declare(strict_types=1);

use GeorgianTax\Domain\Asset\Asset;
use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\SelfSupply;
use GeorgianTax\Domain\Event\SelfSupplyReason;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Income\SupplyCategory as C;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Residency;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Law\Article;
use GeorgianTax\Tests\Support\VatCase;
use GeorgianTax\Vat\Vat;
use Mchekhashvili\Money\Money;

/** One test per row of the VAT section of docs/CATALOGUE.md. */
uses(VatCase::class);

/* ── Registered suppliers ── */

test('registered supplier charges 18 percent', function () {
    $report = $this->process($this->pay($this->buyer, $this->seller, K::GoodsSupply, 1000));
    $vat = $report->for('vat');

    $this->assertCharged($vat, '168.1', Collection::MonthlyDeclaration, 180);
    $this->assertSame('buyer', $vat->creditableBy?->id);
    $this->assertSame(18000, $report->vatCharged()->minor);
});

test('price including vat is split out', function () {
    $event = new Pay($this->buyer, $this->seller, K::ServiceFee, Money::GEL(1180), $this->today, pricesIncludeVat: true);

    $this->assertCharged($this->vat($event), '168.1', Collection::MonthlyDeclaration, 180);
});

test('consumer cannot credit', function () {
    $vat = $this->vat($this->pay(Party::person('nino'), $this->seller, K::GoodsSupply, 100));

    $this->assertSame(null, $vat->creditableBy);
});

/* ── Registration threshold ── */

test('unregistered supplier below threshold charges nothing but counts', function () {
    $shop = Party::company('shop');

    $this->assertNotTaxable($this->process($this->pay($this->buyer, $shop, K::GoodsSupply, 60_000))->for('vat'), '165.1');
    $this->assertSame(6_000_000, $this->ledger->turnover($shop, 'vat', $this->today->modify('-1 year'))->minor);
});

test('supply crossing 100k is taxed from that supply', function () {
    $shop = Party::company('shop');
    $this->ledger->seedTurnover($shop, 'vat', new DateTimeImmutable('2026-03-01'), Money::GEL(98_000));

    $this->assertCharged($this->vat($this->pay($this->buyer, $shop, K::GoodsSupply, 5000)), '165.1', Collection::MonthlyDeclaration, 900);
});

test('turnover older than 12 months does not count', function () {
    $shop = Party::company('shop');
    $this->ledger->seedTurnover($shop, 'vat', new DateTimeImmutable('2025-06-01'), Money::GEL(98_000));

    $this->assertNotTaxable($this->vat($this->pay($this->buyer, $shop, K::GoodsSupply, 5000)), '165.1');
});

test('fixed establishment owes from first supply', function () {
    $branch = new Party('branch', PartyKind::Company, Residency::NonResident, 'DE', hasGeorgianPE: true);

    $this->assertCharged($this->vat($this->pay($this->buyer, $branch, K::ServiceFee, 1000)), '165.9', Collection::MonthlyDeclaration, 180);
});

/* ── Cross-border services ── */

test('foreign service to georgian business is reverse charged', function () {
    $client = Party::company('acme');
    $event = new Pay($client, Party::foreignCompany('gmbh', 'DE'), K::ServiceFee, Money::GEL(1000), $this->today, Place::abroad('DE'));
    $vat = $this->vat($event);

    $this->assertCharged($vat, '161.1.ა', Collection::ReverseCharge, 180);
    $this->assertSame('acme', $vat->agent?->id);
    $this->assertSame(null, $vat->creditableBy); // acme is not VAT-registered
});

test('registered customer credits its reverse charge', function () {
    $event = new Pay($this->buyer, Party::foreignCompany('gmbh', 'DE'), K::ServiceFee, Money::GEL(1000), $this->today, Place::abroad('DE'));

    $this->assertSame('buyer', $this->vat($event)->creditableBy?->id);
});

test('foreign electronic service to georgian consumer is on the supplier', function () {
    $event = new Pay(Party::person('nino'), Party::foreignCompany('streaming', 'US'), K::ServiceFee, Money::GEL(30), $this->today, Place::abroad('US'), supply: C::ElectronicService);

    $this->assertCharged($this->vat($event), '165².2.ე', Collection::MonthlyDeclaration);
});

test('service to a foreign business is supplied abroad', function () {
    $this->assertNotTaxable($this->vat($this->pay(Party::foreignCompany('gmbh', 'DE'), $this->seller, K::ServiceFee, 1000)), '162¹.3.ა');
});

test('consulting for a foreign consumer is supplied abroad', function () {
    $event = new Pay(Party::foreigner('john', 'US'), $this->seller, K::ServiceFee, Money::GEL(1000), $this->today, supply: C::Consulting);

    $this->assertNotTaxable($this->vat($event), '162¹.14');
});

test('restaurant meal for a tourist is taxed where served', function () {
    $event = new Pay(Party::foreigner('john', 'US'), $this->seller, K::ServiceFee, Money::GEL(100), $this->today, supply: C::Restaurant);

    $this->assertCharged($this->vat($event), '168.1', Collection::MonthlyDeclaration, 18);
});

/* ── Goods leaving and entering ── */

test('export is exempt with credit and counts', function () {
    $shop = Party::company('exporter');
    $event = new Pay(Party::foreignCompany('importer', 'AM'), $shop, K::GoodsSupply, Money::GEL(50_000), $this->today, Place::exportTo('AM'));

    $this->assertExemptWithCredit($this->process($event)->for('vat'), '172.4.ე');
    $this->assertSame(5_000_000, $this->ledger->turnover($shop, 'vat', $this->today->modify('-1 year'))->minor);
});

test('import pays at customs on value plus duties', function () {
    $import = new Import($this->buyer, Money::GEL(10_000), $this->today, dutiesAndFees: Money::GEL(1000));
    $vat = $this->vat($import);

    $this->assertCharged($vat, '165².2.ა', Collection::AtCustoms, 1980);
    $this->assertSame('buyer', $vat->creditableBy?->id);
});

test('listed import is due in 45 days', function () {
    $vat = $this->vat(new Import($this->buyer, Money::GEL(10_000), $this->today, onDeferredPaymentList: true));

    $this->assertSame('2026-12-14', $vat->due->format('Y-m-d'));
});

test('passenger car import is exempt', function () {
    $this->assertExempt($this->vat(new Import(Party::person('nino'), Money::GEL(15_000), $this->today, goods: C::PassengerCarOrMotorcycle)), '173.ზ');
});

/* ── Exemptions ── */

test('medical care is exempt without credit', function () {
    $this->assertExempt($this->vat(new Pay(Party::person('nino'), Party::company('clinic'), K::ServiceFee, Money::GEL(200), $this->today, supply: C::MedicalCare)), '170.1.ა–გ');
});

test('interest is a financial service exempt without credit', function () {
    $this->assertExempt($this->vat($this->pay(Party::person('nino'), Party::company('bank', Status::VatPayer), K::Interest, 100)), '171.1.ა');
});

test('selling land is exempt', function () {
    $land = new Asset(AssetType::Land, new DateTimeImmutable('2020-01-01'), Money::GEL(50_000));

    $this->assertExempt($this->vat(new Sell($this->seller, $this->buyer, $land, Money::GEL(80_000), $this->today)), '171.1.გ');
});

test('supply to a diplomatic mission is exempt with credit', function () {
    $embassy = Party::company('embassy', Status::DiplomaticMission);

    $this->assertExemptWithCredit($this->vat($this->pay($embassy, $this->seller, K::GoodsSupply, 1000)), '172.3');
});

test('primary agricultural produce is exempt with credit', function () {
    $event = new Pay($this->buyer, $this->seller, K::GoodsSupply, Money::GEL(1000), $this->today, supply: C::PrimaryAgriculturalProduce);

    $this->assertExemptWithCredit($this->vat($event), '172.4.უ');
});

/* ── Not a supply ── */

test('salary is not a supply', function () {
    $this->assertNotTaxable($this->vat($this->pay($this->seller, Party::person('nino'), K::Salary, 3000)), '159');
});

test('individual selling their car is not economic activity', function () {
    $car = new Asset(AssetType::Car, new DateTimeImmutable('2024-01-01'), Money::GEL(20_000));

    $this->assertNotTaxable($this->vat(new Sell(Party::person('nino'), $this->buyer, $car, Money::GEL(18_000), $this->today)), '158.2.ბ');
});

test('selling a non residential building is always economic activity', function () {
    $office = new Asset(AssetType::RealEstate, new DateTimeImmutable('2024-01-01'), Money::GEL(300_000));
    $vat = $this->vat(new Sell(Party::person('nino'), $this->buyer, $office, Money::GEL(500_000), $this->today));

    $this->assertCharged($vat, '165.1', Collection::MonthlyDeclaration, 90_000);
});

/* ── Deemed supplies ── */

test('gift by a vat payer is a deemed supply', function () {
    $this->assertCharged($this->vat(new Gift($this->seller, Party::person('nino'), Money::GEL(500), $this->today)), '168.1', Collection::MonthlyDeclaration, 90);
});

test('small gift is not a supply', function () {
    $this->assertNotTaxable($this->vat(new Gift($this->seller, Party::person('nino'), Money::GEL(40), $this->today)), '157.ი');
});

test('free transfer to the state is exempt with credit', function () {
    $this->assertExemptWithCredit($this->vat(new Gift($this->seller, Party::state(), Money::GEL(5000), $this->today)), '172.4.გ');
});

test('stock left on deregistration is taxed', function () {
    $event = new SelfSupply($this->seller, Money::GEL(10_000), $this->today, SelfSupplyReason::Deregistration);

    $this->assertCharged($this->vat($event), '168.1', Collection::MonthlyDeclaration, 1800);
});

test('margin scheme taxes the margin without credit', function () {
    $dealer = Party::company('dealer', Status::VatPayer, Status::TaxableDealer);
    $usedCar = new Asset(AssetType::Other, new DateTimeImmutable('2026-09-01'), Money::GEL(10_000));
    $vat = $this->vat(new Sell($dealer, $this->buyer, $usedCar, Money::GEL(11_180), $this->today, marginScheme: true));

    $this->assertCharged($vat, '161²', Collection::MonthlyDeclaration, 180);
    $this->assertSame(null, $vat->creditableBy);
});

/* ── Engine, refunds, traceability ── */

test('one event runs through both taxes', function () {
    $report = $this->process($this->pay($this->seller, Party::person('nino'), K::ServiceFee, 1000));

    $this->assertInstanceOf(Taxable::class, $report->for('income'));        // 154.1.დ withheld
    $this->assertNotTaxable($report->for('vat'), '165.1');                 // unregistered, below threshold
});

test('refund reverses vat', function () {
    $sale = $this->pay($this->buyer, $this->seller, K::GoodsSupply, 1000);
    $vat = $this->vat(new Refund($sale, $this->today));

    $this->assertSame(-18000, $vat->amount->minor);
});

test('every vat rule and place rule cites an article', function () {
    $root = dirname(__DIR__, 2) . '/src/Vat';
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
});
