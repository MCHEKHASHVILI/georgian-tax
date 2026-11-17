<?php

declare(strict_types=1);

use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\PayPropertyInstalment;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Domain\Property\PropertyExemption as X;
use GeorgianTax\Domain\Property\PropertyItem;
use GeorgianTax\Domain\Property\PropertyKind as K;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\PropertyTax\MunicipalRates;
use GeorgianTax\Tests\Support\PropertyTaxCase;
use Mchekhashvili\Money\Money;

/** One test per row of the property tax section of docs/CATALOGUE.md — run through all six taxes. */
uses(PropertyTaxCase::class);

/* ── Who pays, on what (201, 203) ── */

test('only holdings carry property tax', function () {
    $sale = new Pay($this->company, $this->person, IncomeKind::GoodsSupply, Money::GEL(100), new DateTimeImmutable('2026-10-30'));

    $this->assertNotTaxable($this->property($sale), '201, 203');
});

test('holding is ignored by every other tax', function () {
    $report = $this->engine->process($this->own($this->company, new PropertyItem(K::Equipment, Money::GEL(100_000))));

    foreach (['import', 'excise', 'vat', 'income', 'profit'] as $tax) {
        $this->assertSame(0, $report->amountOf($tax)->minor, $tax);
    }

    $this->assertSame(100_000, $report->totalTax()->minor);
});

test('state pays none', function () {
    $this->assertNotTaxable($this->property($this->own(Party::state(), new PropertyItem(K::Building, Money::GEL(1_000_000)))), '201, 203');
});

test('individual equipment outside business is not taxable', function () {
    $this->assertNotTaxable($this->property($this->own($this->person, new PropertyItem(K::Equipment, Money::GEL(50_000)), income: 200_000)), '201.1.გ');
});

/* ── Company property (202.1–3¹) ── */

test('company equipment at 1 percent', function () {
    $tax = $this->property($this->own($this->company, new PropertyItem(K::Equipment, Money::GEL(100_000))));

    $this->assertTaxed($tax, '202.1', 1000);
    $this->assertSame('2027-04-01', $tax->due->format('Y-m-d'));
});

test('old real estate is revalued three times', function () {
    $building = new PropertyItem(K::Building, Money::GEL(100_000), acquiredYear: 1998);

    $this->assertTaxed($this->property($this->own($this->company, $building)), '202.1', 3000);
});

test('real estate from 2000 to 2003 twice', function () {
    $this->assertTaxed($this->property($this->own($this->company, new PropertyItem(K::Building, Money::GEL(100_000), acquiredYear: 2002))), '202.1', 2000);
});

test('real estate from 2004 one and a half', function () {
    $this->assertTaxed($this->property($this->own($this->company, new PropertyItem(K::Building, Money::GEL(100_000), acquiredYear: 2004))), '202.1', 1500);
});

test('real estate of unknown date three times', function () {
    $this->assertTaxed($this->property($this->own($this->company, new PropertyItem(K::Building, Money::GEL(100_000)))), '202.1', 3000);
});

test('audited revaluation skips the multiplier', function () {
    $building = new PropertyItem(K::Building, Money::GEL(100_000), acquiredYear: 1998, revaluedWithAudit: true);

    $this->assertTaxed($this->property($this->own($this->company, $building)), '202.1', 1000);
});

test('leasing company at 06 percent', function () {
    $car = new PropertyItem(K::PassengerCar, Money::GEL(100_000), leasedOut: true, byLeasingCompany: true);

    $this->assertTaxed($this->property($this->own($this->company, $car)), '202.3¹', 600);
});

test('company existing part of the year pays proportionally', function () {
    $tax = $this->property($this->own($this->company, new PropertyItem(K::Equipment, Money::GEL(120_000)), months: 6));

    $this->assertTaxed($tax, '202.1', 600);
});

test('council may charge less', function () {
    $engine = $this->engineWith(new MunicipalRates(companyPercent: 0.5));
    $tax = $engine->process($this->own($this->company, new PropertyItem(K::Equipment, Money::GEL(100_000))))->for('property');

    $this->assertTaxed($tax, '202.1', 500);
});

test('council cannot go outside the caps in force on the day', function () {
    $cases = [
        [['companyPercent' => 1.2], $this->own($this->company, new PropertyItem(K::Equipment, Money::GEL(100_000))), "The council's 1.2% exceeds the 202.1 cap of 1% in force on 2026-12-31."],
        [['leasingCompanyPercent' => 0.7], $this->own($this->company, new PropertyItem(K::Equipment, Money::GEL(100_000), leasedOut: true, byLeasingCompany: true)), "The council's 0.7% exceeds the 202.3¹ cap of 0.6% in force on 2026-12-31."],
        [['individualPercent' => 0.5], $this->own($this->person, new PropertyItem(K::Building, Money::GEL(500_000)), income: 150_000), "The council's 0.5% is below the 202.5 minimum of 0.8% in force on 2026-12-31."],
        [['landMultipliers' => ['gori' => 2.0]], $this->own($this->person, PropertyItem::land(K::Arable, 1, 'Gori')), "The council's 2× exceeds the 204.1.დ cap of 1.5× in force on 2026-04-01."],
        [['territorialCoefficients' => ['tbilisi' => 1.6]], $this->own($this->company, PropertyItem::land(K::NonAgriculturalLand, 1000, 'Tbilisi')), "The council's 1.6× exceeds the 204.2.ბ cap of 1.5× in force on 2026-04-01."],
        [['naturalResourcePerHa' => 4.0], $this->own($this->company, PropertyItem::land(K::NaturalResourceLand, 50)), "The council's 4 GEL/ha exceeds the 204.3 cap of 3 GEL/ha in force on 2026-04-01."],
    ];

    foreach ($cases as [$decision, $event, $reason]) {
        $tax = $this->engineWith(new MunicipalRates(...$decision))->process($event)->for('property');

        $this->assertInstanceOf(Unresolved::class, $tax, json_encode($decision) . ': ' . $tax);
        $this->assertSame($reason, $tax->reason);
    }
});

test('a council decision no law could allow is refused at once', function () {
    new MunicipalRates(companyPercent: -1);
})->throws(InvalidArgumentException::class);

test('council may charge less than the natural resource cap', function () {
    $engine = $this->engineWith(new MunicipalRates(naturalResourcePerHa: 2.5));

    $this->assertLevied($engine->process($this->own($this->company, PropertyItem::land(K::NaturalResourceLand, 50)))->for('property'), '204.3', 125);
});

/* ── Individual property (202.5, 206.1.ა) ── */

test('individual with family income up to 100k is exempt', function () {
    $this->assertExempt($this->property($this->own($this->person, new PropertyItem(K::Building, Money::GEL(500_000)), income: 100_000)), '206.1.ა');
});

test('individual above 100k pays on market value', function () {
    $tax = $this->property($this->own($this->person, new PropertyItem(K::Building, Money::GEL(500_000)), income: 150_000));

    $this->assertTaxed($tax, '202.5', 5000);
    $this->assertSame('2027-11-15', $tax->due->format('Y-m-d'));
});

test('individual owning part of the year', function () {
    $this->assertTaxed($this->property($this->own($this->person, new PropertyItem(K::PassengerCar, Money::GEL(60_000)), months: 4, income: 150_000)), '202.5', 200);
});

test('individual at 08 percent where the council says so', function () {
    $engine = $this->engineWith(new MunicipalRates(individualPercent: 0.8));
    $tax = $engine->process($this->own($this->person, new PropertyItem(K::Building, Money::GEL(500_000)), income: 150_000))->for('property');

    $this->assertTaxed($tax, '202.5', 4000);
});

test('individual without family income is unresolved', function () {
    $this->assertInstanceOf(Unresolved::class, $this->property($this->own($this->person, new PropertyItem(K::Building, Money::GEL(500_000)))));
});

/* ── Land (204) ── */

test('arable land in marneuli', function () {
    $tax = $this->property($this->own($this->person, PropertyItem::land(K::Arable, 2.5, 'Marneuli')));

    $this->assertLevied($tax, '204.1.ა', 250);
    $this->assertSame('2026-11-15', $tax->due->format('Y-m-d'));
});

test('land is taxed whatever the family income', function () {
    $this->assertLevied($this->property($this->own($this->person, PropertyItem::land(K::Arable, 1, 'Kutaisi'), income: 10_000)), '204.1.ა', 71);
});

test('tbilisi village rate', function () {
    $this->assertLevied($this->property($this->own($this->person, PropertyItem::land(K::Homestead, 1, 'tbilisi:Kojori'))), '204.1.ა', 95);
});

test('hayfield and pasture', function () {
    $this->assertLevied($this->property($this->own($this->person, PropertyItem::land(K::Hayfield, 10, 'Dedoplistskaro'))), '204.1.ბ', 200);
    $this->assertLevied($this->property($this->own($this->person, PropertyItem::land(K::Pasture, 10, 'Dedoplistskaro'))), '204.1.ბ', 70);
});

test('council multiplier on agricultural land', function () {
    $engine = $this->engineWith(new MunicipalRates(landMultipliers: ['marneuli' => 1.5]));
    $tax = $engine->process($this->own($this->person, PropertyItem::land(K::Arable, 1, 'Marneuli')))->for('property');

    $this->assertLevied($tax, '204.1.ა', 150);
});

test('unknown municipality is unresolved', function () {
    $this->assertInstanceOf(Unresolved::class, $this->property($this->own($this->person, PropertyItem::land(K::Pasture, 1, 'Atlantis'))));
});

test('non agricultural land per square metre', function () {
    $this->assertLevied($this->property($this->own($this->company, PropertyItem::land(K::NonAgriculturalLand, 1000, 'Tbilisi'))), '204.2', 240);
});

test('territorial coefficient', function () {
    $engine = $this->engineWith(new MunicipalRates(territorialCoefficients: ['tbilisi' => 1.5]));
    $tax = $engine->process($this->own($this->company, PropertyItem::land(K::NonAgriculturalLand, 1000, 'Tbilisi')))->for('property');

    $this->assertLevied($tax, '204.2', 360);
});

test('natural resource land', function () {
    $this->assertLevied($this->property($this->own($this->company, PropertyItem::land(K::NaturalResourceLand, 50))), '204.3', 150);
});

/* ── Exemptions (206) ── */

test('organization property outside business is exempt', function () {
    $charity = Party::company('fund')->withKind(PartyKind::Organization);

    $this->assertExempt($this->property($this->own($charity, new PropertyItem(K::Building, Money::GEL(1_000_000)))), '206.1.ე');
    $this->assertTaxed($this->property($this->own($charity, new PropertyItem(K::Equipment, Money::GEL(100_000), usedInBusiness: true))), '202.1', 1000);
});

test('free industrial zone is exempt', function () {
    $this->assertExempt($this->property($this->own(Party::company('fiz', Status::FreeIndustrialZone), new PropertyItem(K::Equipment, Money::GEL(100_000)))), '206.1.ჩ');
});

test('yachts are exempt', function () {
    $this->assertExempt($this->property($this->own($this->person, new PropertyItem(K::Yacht, Money::GEL(300_000)), income: 500_000)), '206.1.ჰ3');
});

test('bankrupt is exempt', function () {
    $this->assertExempt($this->property($this->own(Party::company('bust', Status::Bankrupt), new PropertyItem(K::Equipment, Money::GEL(100_000)))), '206.1.ჰ4');
});

test('asserted exemption', function () {
    $this->assertExempt($this->property($this->own($this->company, new PropertyItem(K::Equipment, Money::GEL(100_000), exemption: X::MedicalProperty))), '206.1.წ');
});

test('expired exemption no longer applies', function () {
    $hotel = new PropertyItem(K::Building, Money::GEL(100_000), acquiredYear: 2020, exemption: X::TouristZoneHotel);

    $this->assertTaxed($this->property($this->own($this->company, $hotel)), '202.1', 1000);
});

test('leased out land loses its exemption', function () {
    $plot = PropertyItem::land(K::Arable, 3, 'Gori', X::SmallFarmOwnedSince2004);
    $leased = PropertyItem::land(K::Arable, 3, 'Gori', X::SmallFarmOwnedSince2004, leasedOut: true);

    $this->assertExempt($this->property($this->own($this->person, $plot)), '206.1.ტ');
    $this->assertLevied($this->property($this->own($this->person, $leased)), '204.1.ა', 246);
});

test('state university keeps its exemption when leasing', function () {
    $building = new PropertyItem(K::Building, Money::GEL(1_000_000), leasedOut: true, exemption: X::StateUniversity);

    $this->assertExempt($this->property($this->own(Party::company('tsu'), $building)), '206.1.ჰ⁵');
});

/* ── Current payment (205.3–4, 205.6, 205.9) ── */

test('a company pays last years tax as a current payment by 15 June', function () {
    $payment = $this->property(new PayPropertyInstalment($this->company, 2026, Money::GEL(12_000)));

    $this->assertLevied($payment, '205.3', 12_000);
    $this->assertSame('2026-06-15', $payment->due->format('Y-m-d'));
    $this->assertSame('acme', $payment->taxpayer->id);
    $this->assertStringContainsString('declared by 1 April 2027', implode(' ', $payment->notes));
});

test('the current payment is answered by property tax alone', function () {
    $report = $this->engine->process(new PayPropertyInstalment($this->company, 2026, Money::GEL(12_000)));

    foreach (['import', 'excise', 'vat', 'income', 'profit'] as $tax) {
        $this->assertSame(0, $report->amountOf($tax)->minor, $tax);
    }

    $this->assertSame(1_200_000, $report->totalTax()->minor);
});

test('a company with no tax last year pays nothing', function () {
    $this->assertNotTaxable($this->property(new PayPropertyInstalment($this->company, 2026, Money::GEL(0))), '205.3');
});

test('individuals and the state make no current payment', function () {
    $this->assertNotTaxable($this->property(new PayPropertyInstalment($this->person, 2026, Money::GEL(500))), '205.3');
    $this->assertNotTaxable($this->property(new PayPropertyInstalment(Party::person('gia')->withKind(PartyKind::IndividualEntrepreneur), 2026, Money::GEL(500))), '205.3');
    $this->assertNotTaxable($this->property(new PayPropertyInstalment(Party::state(), 2026, Money::GEL(500))), '205.3');
});

test('a company founded after the year began makes no current payment for it', function () {
    $founded = new PayPropertyInstalment($this->company, 2026, Money::GEL(0), foundedOn: new DateTimeImmutable('2026-03-10'));
    $onNewYear = new PayPropertyInstalment($this->company, 2026, Money::GEL(5_000), foundedOn: new DateTimeImmutable('2026-01-01'));
    $earlier = new PayPropertyInstalment($this->company, 2026, Money::GEL(5_000), foundedOn: new DateTimeImmutable('2025-03-10'));

    $this->assertNotTaxable($this->property($founded), '205.9');
    $this->assertLevied($this->property($onNewYear), '205.3', 5_000);
    $this->assertLevied($this->property($earlier), '205.3', 5_000);
});

test('an expected fall of half or more, told by 1 June, lets the company pay nothing', function () {
    $event = new PayPropertyInstalment($this->company, 2026, Money::GEL(10_000), Money::GEL(5_000), new DateTimeImmutable('2026-06-01'));

    $this->assertNotTaxable($this->property($event), '205.4');
});

test('or pay the reduced amount it chose', function () {
    $event = new PayPropertyInstalment($this->company, 2026, Money::GEL(10_000), Money::GEL(3_000), new DateTimeImmutable('2026-05-20'), reducedTo: Money::GEL(3_000));

    $payment = $this->property($event);

    $this->assertLevied($payment, '205.4', 3_000);
    $this->assertSame('2026-06-15', $payment->due->format('Y-m-d'));
});

test('a fall of less than half is not a reason to reduce', function () {
    $event = new PayPropertyInstalment($this->company, 2026, Money::GEL(10_000), Money::GEL(5_001), new DateTimeImmutable('2026-05-01'));

    $payment = $this->property($event);

    $this->assertLevied($payment, '205.3', 10_000);
    $this->assertStringContainsString('not at least 50% below', implode(' ', $payment->notes));
});

test('a reduction told after 1 June, or not told, does not count', function () {
    $late = new PayPropertyInstalment($this->company, 2026, Money::GEL(10_000), Money::GEL(1_000), new DateTimeImmutable('2026-06-02'));
    $untold = new PayPropertyInstalment($this->company, 2026, Money::GEL(10_000), Money::GEL(1_000));

    $this->assertLevied($this->property($late), '205.3', 10_000);
    $this->assertStringContainsString('was told on 2026-06-02, not by 2026-06-01', implode(' ', $this->property($late)->notes));
    $this->assertLevied($this->property($untold), '205.3', 10_000);
});

test('after a change of rate the company may pay at the years rate', function () {
    $event = new PayPropertyInstalment($this->company, 2026, Money::GEL(10_000), atCurrentRates: Money::GEL(8_000));

    $this->assertLevied($this->property($event), '205.6', 8_000);
});

test('a current payments figures cannot be negative, nor reduced above last years tax', function () {
    expect(fn () => new PayPropertyInstalment($this->company, 2026, Money::GEL(-1)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PayPropertyInstalment($this->company, 2026, Money::GEL(100), reducedTo: Money::GEL(101)))->toThrow(InvalidArgumentException::class);
});

test('every rule and stage cites an article', function () {
    foreach (['Rule\AgriculturalLand', 'Rule\OtherLand', 'Rule\CompanyProperty', 'Rule\IndividualProperty', 'Stage\CurrentPayment', 'Stage\IdentifyTaxpayer', 'Stage\ApplyExemptions'] as $class) {
        $this->assertNotEmpty((new ReflectionClass("GeorgianTax\\PropertyTax\\$class"))->getAttributes(Article::class), $class);
    }
});
