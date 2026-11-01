<?php

declare(strict_types=1);

use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Goods\ImportRelief;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\Outcome\Unresolved;
use GeorgianTax\Law\Article;
use GeorgianTax\Tests\Support\ImportTaxCase;
use Mchekhashvili\Money\Money;

/** One test per row of the import tax section of docs/CATALOGUE.md. */
uses(ImportTaxCase::class);

/* ── Who pays, on what (195–197) ── */

test('only imports carry import tax', function () {
    $this->assertNotTaxable($this->tax(new Pay($this->importer, Party::company('shop'), K::GoodsSupply, Money::GEL(100), $this->today)), '195–196');
});

test('import without hs code is unresolved', function () {
    $this->assertInstanceOf(Unresolved::class, $this->tax(new Import($this->importer, Money::GEL(1000), $this->today)));
});

test('unlisted goods are at zero', function () {
    $this->assertNotTaxable($this->tax($this->import(new Commodity('8471 30'))), '197');
});

test('listed meat at 12 percent', function () {
    $tax = $this->tax($this->import(new Commodity('0201 30'), 10_000));

    $this->assertTaxed($tax, '197.1', 1200);
    $this->assertSame(Collection::AtCustoms, $tax->collection);
    $this->assertSame('importer', $tax->taxpayer->id);
});

test('whole chapter 19 at 12 percent', function () {
    $this->assertTaxed($this->tax($this->import(new Commodity('1905 31'), 1000)), '197.1', 120);
});

test('milk only in small packages at 12 percent', function () {
    $this->assertTaxed($this->tax($this->import(new Commodity('0401 10 100 00'), 1000)), '197.1', 120);
    $this->assertNotTaxable($this->tax($this->import(new Commodity('0401 10 900 00'), 1000)), '197');
});

test('cheese at 5 percent', function () {
    $this->assertTaxed($this->tax($this->import(new Commodity('0406 90'), 10_000)), '197.2', 500);
});

test('import tax joins the vat base', function () {
    $report = $this->engine->process($this->import(new Commodity('0201 30'), 10_000));
    $vat = $report->for('vat');

    $this->assertInstanceOf(Taxable::class, $vat);
    $this->assertSame(11_200 * 18, $vat->amount->minor);
});

/* ── Cars (197.6) ── */

test('car per cc plus 5 percent per year', function () {
    // 2,000 cm³ × 0.05 = 100, plus 5% × 7 years = 35
    $this->assertLevied($this->tax($this->import(new Commodity('8703 23', engineCc: 2000, yearMade: 2019))), '197.6', 135);
});

test('new car pays only the per cc part', function () {
    $this->assertLevied($this->tax($this->import(new Commodity('8703 23', engineCc: 1600, yearMade: 2026))), '197.6', 80);
});

test('car without year is unresolved', function () {
    $this->assertInstanceOf(Unresolved::class, $this->tax($this->import(new Commodity('8703 23', engineCc: 2000))));
});

test('car import pays import tax excise and vat', function () {
    $report = $this->engine->process($this->import(new Commodity('8703 23', engineCc: 2000, yearMade: 2019), 20_000));

    $this->assertSame(13_500, $report->for('import')->amount->minor);
    $this->assertSame(900_000, $report->for('excise')->amount->minor);
});

/* ── Alcohol in euro (197.3) ── */

test('wine in small bottles per litre', function () {
    // 100 l × €0.50 × 3.00 GEL
    $this->assertLevied($this->tax($this->import(new Commodity('2204 21', 100, alcoholPercent: 13), eur: 3.0)), '197.3', 150);
});

test('sparkling wine', function () {
    $this->assertLevied($this->tax($this->import(new Commodity('2204 10', 100, alcoholPercent: 12), eur: 3.0)), '197.3', 450);
});

test('spirits per degree per hectolitre', function () {
    // 200 l at 40% = 2 hl × 40 = 80 %vol·hl × €1.50 × 3.00 GEL
    $this->assertLevied($this->tax($this->import(new Commodity('2208 30', 200, alcoholPercent: 40), eur: 3.0)), '197.3', 360);
});

test('alcohol without euro rate is unresolved', function () {
    $this->assertInstanceOf(Unresolved::class, $this->tax($this->import(new Commodity('2208 30', 200, alcoholPercent: 40))));
});

/* ── Exemptions (199) ── */

test('electric car is exempt', function () {
    $this->assertExempt($this->tax($this->import(new Commodity('8703 80', electric: true))), '199.ჟ');
});

test('undenatured alcohol over 80 is exempt by code', function () {
    $this->assertExempt($this->tax($this->import(new Commodity('2207 10 000 00', 1000, alcoholPercent: 96), eur: 3.0)), '199.პ');
});

test('salt is exempt but not talc 2526 20', function () {
    $this->assertExempt($this->tax($this->import(new Commodity('2501 00 310 00'))), '199.პ');
    $this->assertExempt($this->tax($this->import(new Commodity('2526 10'))), '199.პ');
    // 2526 20 is carved out of the exemption, and chapter 25 is on the 12% list
    $this->assertTaxed($this->tax($this->import(new Commodity('2526 20 000 00'), 1000)), '197.1', 120);
});

test('humanitarian aid is exempt', function () {
    $this->assertExempt($this->tax($this->import(new Commodity('0201 30'), 50_000, ImportRelief::Humanitarian)), '199.ა');
});

test('postal relief covers chapters 28 to 97 up to 300', function () {
    $this->assertExempt($this->tax($this->import(new Commodity('3926 10 000 00'), 250, ImportRelief::PostalSmall)), '199.დ.ვ');
    $this->assertTaxed($this->tax($this->import(new Commodity('3926 10 000 00'), 400, ImportRelief::PostalSmall)), '197.2', 20);
});

test('traveller food relief covers food chapters only', function () {
    $this->assertExempt($this->tax($this->import(new Commodity('0201 30'), 400, ImportRelief::TravellerFood)), '199.დ.ა');
    $this->assertTaxed($this->tax($this->import(new Commodity('3926 10 000 00'), 400, ImportRelief::TravellerFood)), '197.2', 20);
});

test('returning resident relief excludes vehicles', function () {
    $car = new Commodity('8703 23', engineCc: 2000, yearMade: 2026);

    $this->assertLevied($this->tax($this->import($car, 10_000, ImportRelief::ReturningResident)), '197.6', 100);
    $this->assertExempt($this->tax($this->import(new Commodity('3926 10 000 00'), 10_000, ImportRelief::ReturningResident)), '199.დ.დ');
});

test('raw tobacco relief ends in 2028', function () {
    $leaf = new Commodity('2401 10', 1000);
    $later = new Import($this->importer, Money::GEL(1000), new DateTimeImmutable('2028-03-01'), commodity: $leaf, relief: ImportRelief::RawTobacco);

    $this->assertExempt($this->tax($this->import($leaf, 1000, ImportRelief::RawTobacco)), '199.ი');
    $this->assertTaxed($this->tax($later), '197.1', 120);
});

/* ── Refund and the law ── */

test('refund reverses the tax', function () {
    $import = $this->import(new Commodity('0201 30'), 10_000);

    $this->assertSame(-120_000, $this->tax(new Refund($import, $this->today))->amount->minor);
});

test('every rule and stage cites an article', function () {
    $checked = 0;

    foreach (['Rule\PassengerCar', 'Rule\Alcohol', 'Rule\ListedRate', 'Stage\IdentifyImport', 'Stage\ApplyExemptions'] as $class) {
        $this->assertNotEmpty((new ReflectionClass("GeorgianTax\\ImportTax\\$class"))->getAttributes(Article::class), $class);
        $checked++;
    }

    $this->assertSame(5, $checked);
});
