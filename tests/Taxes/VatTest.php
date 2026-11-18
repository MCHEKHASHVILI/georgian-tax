<?php

declare(strict_types=1);

use GeorgianTax\Domain\Asset\Asset;
use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\ApportionInputVat;
use GeorgianTax\Domain\Event\CloseFixedAssetYear;
use GeorgianTax\Domain\Event\CorrectApportionment;
use GeorgianTax\Domain\Event\CreditFixedAsset;
use GeorgianTax\Domain\Event\FixedAsset;
use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\SelfSupply;
use GeorgianTax\Domain\Event\SelfSupplyReason;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Event\Turnover;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Income\SupplyCategory as C;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Residency;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Law\Article;
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

/* ── Proportional credit (177) ── */

function vatTurnover(int $creditable, int $nonCreditable): Turnover
{
    return new Turnover(Money::GEL($creditable), Money::GEL($nonCreditable));
}

test('mixed use input vat is credited at last years annual percentage', function () {
    $outcome = $this->vat(new ApportionInputVat($this->seller, 2026, 10, Money::GEL(1_000),
        monthTurnover: vatTurnover(50_000, 50_000), priorYear: vatTurnover(600_000, 200_000)));

    $this->assertInstanceOf(Levied::class, $outcome, (string) $outcome);
    $this->assertSame('177.4.ა', $outcome->basis()->ref);
    $this->assertSame(-75_000, $outcome->amount->minor);                    // 75% of 1,000
    $this->assertSame(0.75, $outcome->quantity);
    $this->assertSame(Collection::MonthlyDeclaration, $outcome->collection);
    $this->assertSame('2026-11-15', $outcome->due->format('Y-m-d'));
    expect(implode(' ', $outcome->notes))->toContain('75%')->toContain('250.00 GEL')->toContain('2025');
});

test('without last years percentage the month fixes its own', function () {
    $outcome = $this->vat(new ApportionInputVat($this->seller, 2026, 3, Money::GEL(900), monthTurnover: vatTurnover(20_000, 10_000)));

    $this->assertSame('177.4.ბ', $outcome->basis()->ref);
    $this->assertSame(-60_000, $outcome->amount->minor);                    // 2/3 of 900
    $this->assertSame('2026-04-15', $outcome->due->format('Y-m-d'));

    // a prior year with no turnover fixed no percentage
    $outcome = $this->vat(new ApportionInputVat($this->seller, 2026, 3, Money::GEL(900), monthTurnover: vatTurnover(20_000, 10_000), priorYear: vatTurnover(0, 0)));
    $this->assertSame('177.4.ბ', $outcome->basis()->ref);
});

test('a month with no turnover to divide is unresolved', function () {
    $outcome = $this->vat(new ApportionInputVat($this->seller, 2026, 1, Money::GEL(500), monthTurnover: vatTurnover(0, 0)));

    $this->assertInstanceOf(Unresolved::class, $outcome, (string) $outcome);
    $this->assertSame('177.4.ბ', $outcome->basis()->ref);
});

test('the months are apportioned even below five percent', function () {
    $outcome = $this->vat(new ApportionInputVat($this->seller, 2026, 5, Money::GEL(1_000), priorYear: vatTurnover(97_000, 3_000)));

    $this->assertSame(-97_000, $outcome->amount->minor);                    // 177.7 is the year's test
});

test('the years last period corrects the credit to the years percentage', function () {
    // 12,000 input, credited 9,000 at last year's 75%; this year 60%: 7,200, so 1,800 given back
    $back = $this->vat(new CorrectApportionment($this->seller, 2026, Money::GEL(12_000), Money::GEL(9_000), vatTurnover(300_000, 200_000)));

    $this->assertInstanceOf(Levied::class, $back, (string) $back);
    $this->assertSame('177.4', $back->basis()->ref);
    $this->assertSame(180_000, $back->amount->minor);
    $this->assertSame('2027-01-15', $back->due->format('Y-m-d'));
    expect(implode(' ', $back->notes))->toContain('60%')->toContain('given back');

    // this year 90%: 10,800, so 1,800 more credited
    $more = $this->vat(new CorrectApportionment($this->seller, 2026, Money::GEL(12_000), Money::GEL(9_000), vatTurnover(450_000, 50_000)));
    $this->assertSame(-180_000, $more->amount->minor);
    expect(implode(' ', $more->notes))->toContain('more is credited');
});

test('under five percent without credit the whole input vat is credited', function () {
    $outcome = $this->vat(new CorrectApportionment($this->seller, 2026, Money::GEL(10_000), Money::GEL(9_600), vatTurnover(96_000, 4_000)));

    $this->assertSame('177.7', $outcome->basis()->ref);
    $this->assertSame(-40_000, $outcome->amount->minor);                    // 10,000 − 9,600

    // exactly 5% is not under it
    $outcome = $this->vat(new CorrectApportionment($this->seller, 2026, Money::GEL(10_000), Money::GEL(9_500), vatTurnover(95_000, 5_000)));
    $this->assertSame('177.4', $outcome->basis()->ref);
    $this->assertTrue($outcome->amount->isZero());
});

test('only a registered vat payer apportions input vat', function () {
    $unregistered = Party::company('shop');

    $this->assertNotTaxable($this->vat(new ApportionInputVat($unregistered, 2026, 1, Money::GEL(100), monthTurnover: vatTurnover(1, 1))), '174.3');
    $this->assertNotTaxable($this->vat(new CorrectApportionment($unregistered, 2026, Money::GEL(100), Money::GEL(0), vatTurnover(1, 1))), '174.3');
});

test('no other tax is charged on apportioned input vat', function () {
    $report = $this->taxEngine()->process(new ApportionInputVat($this->seller, 2026, 10, Money::GEL(1_000), priorYear: vatTurnover(3, 1)));

    expect($report->outcomes->keys()->all())->toContain('vat', 'income', 'profit', 'excise', 'import', 'property');
    foreach ($report->outcomes as $tax => $outcome) {
        if ($tax !== 'vat') {
            expect($outcome)->toBeInstanceOf(NotTaxable::class, "$tax: $outcome");
        }
    }
});

test('apportionment refuses impossible figures', function (Closure $make) {
    $make();
})->with([
    'month 13' => [fn () => new ApportionInputVat(Party::company('x'), 2026, 13, Money::GEL(1), monthTurnover: vatTurnover(1, 1))],
    'negative input' => [fn () => new ApportionInputVat(Party::company('x'), 2026, 1, Money::GEL(-1), monthTurnover: vatTurnover(1, 1))],
    'no turnover given' => [fn () => new ApportionInputVat(Party::company('x'), 2026, 1, Money::GEL(1))],
    'negative turnover' => [fn () => vatTurnover(-1, 1)],
    'negative credit' => [fn () => new CorrectApportionment(Party::company('x'), 2026, Money::GEL(1), Money::GEL(-1), vatTurnover(1, 1))],
])->throws(InvalidArgumentException::class);

/* ── Fixed assets in mixed use (177.5–6, 177.8) ── */

test('a fixed asset is credited in full at once when last years turnover without credit was under 20 percent', function () {
    $building = new FixedAsset(Money::GEL(180_000), realEstate: true, inUseFrom: 2026);
    $outcome = $this->vat(new CreditFixedAsset($this->seller, 2026, 3, $building, vatTurnover(900_000, 100_000)));

    $this->assertInstanceOf(Levied::class, $outcome, (string) $outcome);
    $this->assertSame('177.5.ა', $outcome->basis()->ref);
    $this->assertSame(-18_000_000, $outcome->amount->minor);                // all 180,000 in the first period
    $this->assertSame('2026-04-15', $outcome->due->format('Y-m-d'));
    expect(implode(' ', $outcome->notes))->toContain('20%')->toContain('2026 to 2035')->toContain('177.6.ა');
});

test('each year end gives back a tenth in the years share without credit', function () {
    $building = new FixedAsset(Money::GEL(180_000), realEstate: true, inUseFrom: 2026);
    // a tenth is 18,000; 25% of the year's turnover without credit: 4,500 given back
    $outcome = $this->vat(new CloseFixedAssetYear($this->seller, 2026, $building, vatTurnover(750_000, 250_000), creditedInFull: true));

    $this->assertInstanceOf(Levied::class, $outcome, (string) $outcome);
    $this->assertSame('177.5.ა', $outcome->basis()->ref);
    $this->assertSame(450_000, $outcome->amount->minor);
    $this->assertSame(0.25, $outcome->quantity);
    $this->assertSame('2027-01-15', $outcome->due->format('Y-m-d'));
    expect(implode(' ', $outcome->notes))->toContain('18000.00 GEL')->toContain('25%');

    // the tenth year is the last
    $this->assertSame('177.5.ა', $this->vat(new CloseFixedAssetYear($this->seller, 2035, $building, vatTurnover(750_000, 250_000), true))->basis()->ref);
    $this->assertNotTaxable($this->vat(new CloseFixedAssetYear($this->seller, 2036, $building, vatTurnover(750_000, 250_000), true)), '177.6.ა');
});

test('otherwise nothing is credited at once and each year end credits a fifth in the years share with credit', function () {
    $machine = new FixedAsset(Money::GEL(9_000), realEstate: false, inUseFrom: 2026);

    // last year 30% without credit: not under 20%
    $first = $this->vat(new CreditFixedAsset($this->seller, 2026, 2, $machine, vatTurnover(70_000, 30_000)));
    $this->assertInstanceOf(Levied::class, $first, (string) $first);
    $this->assertSame('177.5.ბ', $first->basis()->ref);
    $this->assertTrue($first->amount->isZero());
    expect(implode(' ', $first->notes))->toContain('not under')->toContain('2026 to 2030');

    // a fifth is 1,800; 60% with credit: 1,080 credited
    $year = $this->vat(new CloseFixedAssetYear($this->seller, 2026, $machine, vatTurnover(600_000, 400_000), creditedInFull: false));
    $this->assertSame('177.5.ბ', $year->basis()->ref);
    $this->assertSame(-108_000, $year->amount->minor);
    $this->assertSame(0.6, $year->quantity);

    // five years from the year put into use
    $this->assertSame(-108_000, $this->vat(new CloseFixedAssetYear($this->seller, 2030, $machine, vatTurnover(600_000, 400_000), false))->amount->minor);
    $this->assertNotTaxable($this->vat(new CloseFixedAssetYear($this->seller, 2031, $machine, vatTurnover(600_000, 400_000), false)), '177.6.ბ');
    $this->assertNotTaxable($this->vat(new CloseFixedAssetYear($this->seller, 2025, $machine, vatTurnover(600_000, 400_000), false)), '177.6.ბ');
});

test('exactly 20 percent or no turnover last year leaves the fixed asset to the year ends', function () {
    $machine = new FixedAsset(Money::GEL(9_000), realEstate: false, inUseFrom: 2026);

    $this->assertSame('177.5.ბ', $this->vat(new CreditFixedAsset($this->seller, 2026, 2, $machine, vatTurnover(80_000, 20_000)))->basis()->ref);
    $this->assertSame('177.5.ბ', $this->vat(new CreditFixedAsset($this->seller, 2026, 2, $machine, vatTurnover(0, 0)))->basis()->ref);

    $none = $this->vat(new CreditFixedAsset($this->seller, 2026, 2, $machine));
    $this->assertSame('177.5.ბ', $none->basis()->ref);
    expect(implode(' ', $none->notes))->toContain('No turnover in 2025');
});

test('a year under five percent without credit takes the years part in full', function () {
    $machine = new FixedAsset(Money::GEL(9_000), realEstate: false, inUseFrom: 2026);

    $back = $this->vat(new CloseFixedAssetYear($this->seller, 2027, $machine, vatTurnover(97_000, 3_000), creditedInFull: true));
    $this->assertSame('177.7', $back->basis()->ref);
    $this->assertTrue($back->amount->isZero());                             // nothing given back

    $credit = $this->vat(new CloseFixedAssetYear($this->seller, 2027, $machine, vatTurnover(97_000, 3_000), creditedInFull: false));
    $this->assertSame('177.7', $credit->basis()->ref);
    $this->assertSame(-180_000, $credit->amount->minor);                    // the whole fifth
});

test('a year with no turnover is unresolved for a fixed asset', function () {
    $machine = new FixedAsset(Money::GEL(9_000), realEstate: false, inUseFrom: 2026);
    $outcome = $this->vat(new CloseFixedAssetYear($this->seller, 2026, $machine, vatTurnover(0, 0), false));

    $this->assertInstanceOf(Unresolved::class, $outcome, (string) $outcome);
    $this->assertSame('177.5', $outcome->basis()->ref);
});

test('a building put into use or repaired as a self supply goes as if bought', function () {
    $built = new FixedAsset(Money::GEL(90_000), realEstate: true, inUseFrom: 2026, selfSupply: SelfSupplyReason::OwnBuildingPutIntoUse);
    $outcome = $this->vat(new CloseFixedAssetYear($this->seller, 2026, $built, vatTurnover(500_000, 500_000), false));

    $this->assertSame('177.5.ბ', $outcome->basis()->ref);
    $this->assertSame(-450_000, $outcome->amount->minor);                   // half of a tenth of 90,000
    expect(implode(' ', $outcome->notes))->toContain('177.8')->toContain('160.3.ბ')->toContain('the building');

    $repair = new FixedAsset(Money::GEL(10_000), realEstate: true, inUseFrom: 2026, selfSupply: SelfSupplyReason::OwnBuildingRepair);
    expect(implode(' ', $this->vat(new CreditFixedAsset($this->seller, 2026, 6, $repair, vatTurnover(95_000, 5_000)))->notes))
        ->toContain('160¹.2.ბ')->toContain('the repair');
});

test('only a registered vat payer credits a fixed asset', function () {
    $unregistered = Party::company('shop');
    $machine = new FixedAsset(Money::GEL(9_000), realEstate: false, inUseFrom: 2026);

    $this->assertNotTaxable($this->vat(new CreditFixedAsset($unregistered, 2026, 1, $machine, vatTurnover(1, 0))), '174.3');
    $this->assertNotTaxable($this->vat(new CloseFixedAssetYear($unregistered, 2026, $machine, vatTurnover(1, 1), false)), '174.3');
});

test('no other tax is charged on a fixed assets credit', function () {
    $machine = new FixedAsset(Money::GEL(9_000), realEstate: false, inUseFrom: 2026);

    foreach ([new CreditFixedAsset($this->seller, 2026, 1, $machine, vatTurnover(9, 1)), new CloseFixedAssetYear($this->seller, 2026, $machine, vatTurnover(1, 1), true)] as $event) {
        foreach ($this->taxEngine()->process($event)->outcomes as $tax => $outcome) {
            if ($tax !== 'vat') {
                expect($outcome)->toBeInstanceOf(NotTaxable::class, "$tax: $outcome");
            }
        }
    }
});

test('a fixed asset refuses impossible figures', function (Closure $make) {
    $make();
})->with([
    'negative vat' => [fn () => new FixedAsset(Money::GEL(-1), true, 2026)],
    'a building that is not real estate' => [fn () => new FixedAsset(Money::GEL(1), false, 2026, SelfSupplyReason::OwnBuildingPutIntoUse)],
    'a self supply 177.8 does not name' => [fn () => new FixedAsset(Money::GEL(1), true, 2026, SelfSupplyReason::Shortage)],
    'month 0' => [fn () => new CreditFixedAsset(Party::company('x'), 2026, 0, new FixedAsset(Money::GEL(1), true, 2026))],
])->throws(InvalidArgumentException::class);

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
