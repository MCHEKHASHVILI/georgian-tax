<?php

declare(strict_types=1);

use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\TemporaryAdmission;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Goods\ImportRelief;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Party\Party;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Law\Article;
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

test('a returning diplomat brings a vehicle in addition, uncapped', function () {
    $car = new Commodity('8703 23', engineCc: 2000, yearMade: 2026);

    $this->assertExempt($this->tax($this->import($car, 40_000, ImportRelief::ReturningDiplomat)), '199.დ.დ');
});

test('returning resident relief stops above 15,000', function () {
    $this->assertTaxed($this->tax($this->import(new Commodity('3926 10 000 00'), 16_000, ImportRelief::ReturningResident)), '197.2', 800);
});

test('raw tobacco relief ends in 2028', function () {
    $leaf = new Commodity('2401 10', 1000);
    $later = new Import($this->importer, Money::GEL(1000), new DateTimeImmutable('2028-03-01'), commodity: $leaf, relief: ImportRelief::RawTobacco);

    $this->assertExempt($this->tax($this->import($leaf, 1000, ImportRelief::RawTobacco)), '199.ი');
    $this->assertTaxed($this->tax($later), '197.1', 120);
});

/* ── Temporary admission (197.4–5) ── */

test('temporary admission pays 3 percent a calendar month of the import tax on release', function () {
    // Meat at 12% of 10,000 would pay 1,200 on release; 30 October to 10 December touches 3 calendar months
    $admission = new TemporaryAdmission($this->import(new Commodity('0201 30'), 10_000), new DateTimeImmutable('2026-12-10'));
    $tax = $this->tax($admission);

    $this->assertLevied($tax, '197.4', 108);
    $this->assertSame(3.0, $tax->quantity);
    $this->assertSame(3600, $tax->ratePerUnit->minor);
    $this->assertSame('2026-11-15', $tax->due->format('Y-m-d'));  // October's share, by the 15th of November
    $this->assertSame('importer', $tax->taxpayer->id);
});

test('a partial calendar month counts in full', function () {
    $meat = $this->import(new Commodity('0201 30'), 10_000);

    // 30 October to 2 November: October and November
    $this->assertLevied($this->tax(new TemporaryAdmission($meat, new DateTimeImmutable('2026-11-02'))), '197.4', 72);
    // One day
    $this->assertLevied($this->tax(new TemporaryAdmission($meat, new DateTimeImmutable('2026-10-30'))), '197.4', 36);
});

test('a procedure ending before the 15th of next month is paid on its last day', function () {
    $meat = $this->import(new Commodity('0201 30'), 10_000);

    $ended = $this->tax(new TemporaryAdmission($meat, new DateTimeImmutable('2026-10-31')));
    $running = $this->tax(new TemporaryAdmission($meat, new DateTimeImmutable('2026-10-31'), ended: false));

    $this->assertSame('2026-10-31', $ended->due->format('Y-m-d'));
    $this->assertSame('2026-11-15', $running->due->format('Y-m-d'));
});

test('the total is capped at the import tax on release', function () {
    $meat = $this->import(new Commodity('0201 30'), 10_000);

    // October 2026 to June 2029: 33 months, 99%
    $this->assertLevied($this->tax(new TemporaryAdmission($meat, new DateTimeImmutable('2029-06-30'))), '197.4', 1188);
    // … to July 2029: 34 months would be 102%
    $capped = $this->tax(new TemporaryAdmission($meat, new DateTimeImmutable('2029-07-01')));
    $this->assertLevied($capped, '197.5', 1200);
    $this->assertSame(34.0, $capped->quantity);
});

test('a car admitted temporarily pays 3 percent of its per cc duty', function () {
    // 2,000 cm³ × 0.05 = 100, plus 5% × 7 years = 135 on release; 3% of it for one month
    $car = $this->import(new Commodity('8703 23', engineCc: 2000, yearMade: 2019));

    $this->assertLevied($this->tax(new TemporaryAdmission($car, new DateTimeImmutable('2026-10-31'))), '197.4', 4.05);
});

test('temporary admission relieved under 199.კ–კ² pays nothing', function () {
    $relieved = $this->import(new Commodity('0201 30'), 10_000, ImportRelief::TemporaryAdmission);

    $this->assertExempt($this->tax(new TemporaryAdmission($relieved, new DateTimeImmutable('2027-03-01'))), '199.კ–კ²');
});

test('goods at zero percent pay nothing when admitted temporarily', function () {
    $this->assertNotTaxable($this->tax(new TemporaryAdmission($this->import(new Commodity('8471 30')), new DateTimeImmutable('2027-03-01'))), '197');
});

test('temporary admission is not an import for vat or excise', function () {
    $car = $this->import(new Commodity('8703 23', engineCc: 2000, yearMade: 2019), 20_000);
    $report = $this->engine->process(new TemporaryAdmission($car, new DateTimeImmutable('2026-11-30')));

    $this->assertInstanceOf(Levied::class, $report->for('import'));
    $this->assertNotTaxable($report->for('vat'), '160²');
    $this->assertNotTaxable($report->for('excise'), '183.ბ');
});

test('temporary admission cannot end before it was registered', function () {
    new TemporaryAdmission($this->import(new Commodity('0201 30')), new DateTimeImmutable('2026-10-29'));
})->throws(InvalidArgumentException::class);

/* ── Refund and the law ── */

test('refund reverses the tax', function () {
    $import = $this->import(new Commodity('0201 30'), 10_000);

    $this->assertSame(-120_000, $this->tax(new Refund($import, $this->today))->amount->minor);
});

test('every rule and stage cites an article', function () {
    $checked = 0;

    foreach (['Rule\PassengerCar', 'Rule\Alcohol', 'Rule\ListedRate', 'Stage\IdentifyImport', 'Stage\ChargeTemporaryAdmission', 'Stage\ApplyExemptions'] as $class) {
        $this->assertNotEmpty((new ReflectionClass("GeorgianTax\\ImportTax\\$class"))->getAttributes(Article::class), $class);
        $checked++;
    }

    $this->assertSame(6, $checked);
});
