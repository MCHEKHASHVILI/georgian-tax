<?php

declare(strict_types=1);

use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Goods\ImportRelief;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Income\SupplyCategory;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\ExemptWithCredit;
use GeorgianTax\Engine\Outcome\Levied;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Excise\Excise;
use GeorgianTax\Excise\Rate\Calls;
use GeorgianTax\Law\Article;
use GeorgianTax\Tests\Support\ExciseCase;
use Mchekhashvili\Money\Money;

/** One test per row of the excise section of docs/CATALOGUE.md. */
uses(ExciseCase::class);

/* ── Who pays, on what ── */

test('no goods no excise', function () {
    $this->assertNotTaxable($this->excise(new Pay($this->shop, $this->importer, K::ServiceFee, Money::GEL(100), $this->today)), '183');
});

test('non excise goods are not taxable', function () {
    $this->assertNotTaxable($this->excise($this->import(new Commodity('0901 21', 100))), '188');
});

test('reseller pays no excise', function () {
    $sale = $this->sell($this->shop, new Commodity('2208 30', 10, alcoholPercent: 40));

    $this->assertNotTaxable($this->excise($sale), '182–184');
});

test('producer supply is due by the 15th of next month', function () {
    $duty = $this->excise($this->sell($this->producer, new Commodity('2208 30', 10, alcoholPercent: 40)));

    $this->assertLevied($duty, '188¹.1', 90);
    $this->assertSame(Collection::MonthlyDeclaration, $duty->collection);
    $this->assertSame('2026-11-15', $duty->due->format('Y-m-d'));
});

test('import pays at customs', function () {
    $duty = $this->excise($this->import(new Commodity('2710 12', 2)));

    $this->assertLevied($duty, '188.1', 1000);
    $this->assertSame(Collection::AtCustoms, $duty->collection);
    $this->assertSame('importer', $duty->taxpayer->id);
});

test('excise joins the vat base on import', function () {
    $report = $this->engine->process($this->import(new Commodity('2710 12', 2), customs: 4000));
    $vat = $report->for('vat');

    $this->assertInstanceOf(Taxable::class, $vat);
    $this->assertSame((4000 + 1000) * 18, $vat->amount->minor);
});

/* ── Exemptions (194) ── */

test('export is exempt with credit', function () {
    $sale = new Pay(Party::foreignCompany('buyer', 'DE'), $this->producer, K::GoodsSupply, Money::GEL(5000), $this->today,
        Place::exportTo('DE'), commodity: new Commodity('2208 20', 100, alcoholPercent: 40));

    $this->assertInstanceOf(ExemptWithCredit::class, $this->excise($sale));
    $this->assertSame('194.6.ა', $this->excise($sale)->basis()->ref);
});

test('duty free supply is exempt with credit', function () {
    $sale = new Pay($this->shop, $this->producer, K::GoodsSupply, Money::GEL(500), $this->today,
        supply: SupplyCategory::DutyFree, commodity: new Commodity('2208 30', 10, alcoholPercent: 40));

    $outcome = $this->excise($sale);
    $this->assertInstanceOf(ExemptWithCredit::class, $outcome);
    $this->assertSame('194.6.ბ', $outcome->basis()->ref);
});

test('electric left hand drive car is exempt', function () {
    $this->assertExempt($this->excise($this->import(new Commodity('8703 80', electric: true))), '194.5.ლ');
});

test('fuel in the tank is exempt', function () {
    $this->assertExempt($this->excise($this->import(new Commodity('2710 19 43', 0.05), relief: ImportRelief::FuelInVehicleTank)), '194.5.გ');
});

test('postal relief stops above its limit', function () {
    $small = $this->import(new Commodity('2208 30', 1, alcoholPercent: 40), customs: 200, relief: ImportRelief::PostalSmall);
    $large = $this->import(new Commodity('2208 30', 1, alcoholPercent: 40), customs: 400, relief: ImportRelief::PostalSmall);

    $this->assertExempt($this->excise($small), '194.5.ბ¹');
    $this->assertInstanceOf(Levied::class, $this->excise($large));
});

/* ── Cars (188.9–18) ── */

test('new car at 150 per cc', function () {
    $this->assertLevied($this->excise($this->car(engineCc: 2000, yearMade: 2022)), '188.12', 3000);
});

test('car over six years at 450 per cc', function () {
    $this->assertLevied($this->excise($this->car(engineCc: 2000, yearMade: 2019)), '188.13', 9000);
});

test('right hand drive triples the rate', function () {
    $this->assertLevied($this->excise($this->car(engineCc: 2000, yearMade: 2019, rightHandDrive: true)), '188.16', 27_000);
});

test('young left hand hybrid is 60 percent less', function () {
    $this->assertLevied($this->excise($this->car(engineCc: 2000, yearMade: 2023, hybrid: true)), '188.14', 1200);
});

test('old hybrid gets no reduction', function () {
    $this->assertLevied($this->excise($this->car(engineCc: 2000, yearMade: 2015, hybrid: true)), '188.13', 9000);
});

test('classic car at 1 per cc', function () {
    $this->assertLevied($this->excise($this->car(engineCc: 3000, yearMade: 1975, classicCar: true)), '188.10', 3000);
});

test('sports car is flat 100', function () {
    $this->assertLevied($this->excise($this->car(engineCc: 5000, yearMade: 2024, sportsCar: true)), '188.9', 100);
});

test('right hand electric car is flat 3000', function () {
    $this->assertLevied($this->excise($this->car(electric: true, rightHandDrive: true)), '188.17', 3000);
});

test('car without year cannot be computed', function () {
    $this->assertNotTaxable($this->excise($this->car(engineCc: 2000)), '188.18');
});

/* ── Motorcycles (188.1) ── */

test('motorcycle rate follows age', function () {
    $this->assertLevied($this->excise($this->import(new Commodity('8711 20', engineCc: 250, yearMade: 2026))), '188.1', 375);
    $this->assertLevied($this->excise($this->import(new Commodity('8711 20', engineCc: 250, yearMade: 2023))), '188.1', 175);
    $this->assertLevied($this->excise($this->import(new Commodity('8711 20', engineCc: 250, yearMade: 2000))), '188.1', 600);
});

/* ── Tobacco (188.1, 188.1¹) ── */

test('cigarettes pay specific plus 20 percent of retail', function () {
    // 10,000 sticks = 500 packs: 500 × 2.75 + 500 × 8 × 20% = 1,375 + 800
    $this->assertLevied($this->excise($this->import(new Commodity('2402 20', 10_000, retailPricePer20: 8))), '188.1¹.ა', 2175);
});

test('local quota cigarettes', function () {
    $c = new Commodity('2402 20', 10_000, retailPricePer20: 8, localQuotaCigarettes: true);

    // 500 × 1.30 + 500 × 8 × 15% = 650 + 600
    $this->assertLevied($this->excise($this->sell(Party::company('factory', Status::ExciseProducer), $c)), '188.1¹.ა¹', 1250);
});

test('cigarillos pay 30 percent of retail', function () {
    // 200 sticks = 10 packs: 10 × 2.40 + 10 × 10 × 30%
    $this->assertLevied($this->excise($this->import(new Commodity('2402 10 000 02', 200, retailPricePer20: 10))), '188.1¹.გ', 54);
});

test('cigarettes need a retail price', function () {
    $this->assertNotTaxable($this->excise($this->import(new Commodity('2402 20', 1000))), '188.1¹.ა');
});

test('cigars per stick from the table', function () {
    $this->assertLevied($this->excise($this->import(new Commodity('2402 10 000 01', 100))), '188.1', 210);
});

test('nicotine liquid per ml', function () {
    $this->assertLevied($this->excise($this->import(new Commodity('2404 12', 500))), '188.1', 600);
});

/* ── Alcohol (188¹) ── */

test('beer per litre per degree', function () {
    // 1,000 litres × 5% × 0.12
    $this->assertLevied($this->excise($this->import(new Commodity('2203', 1000, alcoholPercent: 5))), '188¹.1', 600);
});

test('wine carries no excise', function () {
    $this->assertNotTaxable($this->excise($this->import(new Commodity('2204 21', 1000, alcoholPercent: 13))), '188¹.2');
});

test('light fermented drink', function () {
    $this->assertLevied($this->excise($this->import(new Commodity('2206 00 310', 1000, alcoholPercent: 4.5))), '188¹.1', 600);
});

test('stronger fermented drink', function () {
    $this->assertLevied($this->excise($this->import(new Commodity('2206 00 310', 1000, alcoholPercent: 8))), '188¹.1', 5000);
});

test('ethyl alcohol per litre of pure alcohol', function () {
    // 1,000 litres at 96% = 960 litres of pure alcohol × 7.50
    $this->assertLevied($this->excise($this->import(new Commodity('2207 10', 1000, alcoholPercent: 96))), '188¹.1', 7200);
});

test('brandy at 15', function () {
    $this->assertLevied($this->excise($this->import(new Commodity('2208 20', 100, alcoholPercent: 40))), '188¹.1', 600);
});

test('vodka at 15 and whisky at 2250', function () {
    $this->assertLevied($this->excise($this->import(new Commodity('2208 60', 100, alcoholPercent: 40))), '188¹.1', 600);
    $this->assertLevied($this->excise($this->import(new Commodity('2208 30', 100, alcoholPercent: 40))), '188¹.1', 900);
});

/* ── Fuels and gases (188.1, 182.1.დ) ── */

test('diesel by the tonne', function () {
    $this->assertLevied($this->excise($this->import(new Commodity('2710 19 43', 10))), '188.1', 4000);
});

test('bilge water at 100', function () {
    $this->assertLevied($this->excise($this->import(new Commodity('2710 99 000 00', 10))), '188.1', 1000);
});

test('gas sold for vehicles is taxed on supply', function () {
    $sale = $this->sell($this->shop, new Commodity('2711 21', 5));

    $this->assertLevied($this->excise($sale), '188.1', 1000);
});

/* ── Call termination (188.3) ── */

test('mobile call termination', function () {
    $this->assertLevied($this->excise($this->sell(Party::company('telco'), new Commodity(Calls::MOBILE, 10_000))), '188.3', 1500);
    $this->assertLevied($this->excise($this->sell(Party::company('telco'), new Commodity(Calls::FIXED, 10_000))), '188.3', 800);
});

/* ── Refund and the law ── */

test('refund reverses the duty', function () {
    $sale = $this->sell($this->producer, new Commodity('2208 30', 10, alcoholPercent: 40));
    $duty = $this->excise(new Refund($sale, $this->today));

    $this->assertSame(-9000, $duty->amount->minor);
});

test('every rate rule cites an article', function () {
    $root = dirname(__DIR__, 2) . '/src/Excise/Rate';
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
});
