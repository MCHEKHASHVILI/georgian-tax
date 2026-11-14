<?php

declare(strict_types=1);

use GeorgianTax\Database\Seeders\GeorgiaLegislationSeeder;
use GeorgianTax\Domain\Event\Expense;
use GeorgianTax\Domain\Event\ExpenseNature;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\OwnProperty;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\ReexportVehicle;
use GeorgianTax\Domain\Event\TemporaryAdmission;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Goods\ImportRelief;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Relief;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Domain\Property\PropertyItem;
use GeorgianTax\Domain\Property\PropertyKind;
use GeorgianTax\Excise\Rate\Calls;
use GeorgianTax\Facades\GeorgianTax;
use GeorgianTax\Ledger\CompanyBooks;
use GeorgianTax\Ledger\StaticBooks;
use GeorgianTax\PropertyTax\MunicipalRates;
use GeorgianTax\PropertyTax\PropertyCaps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Actions\AmendLaw;
use Mchekhashvili\TaxLegislation\Actions\ApproveDraft;
use Mchekhashvili\TaxLegislation\Actions\RecordLegalAct;
use Mchekhashvili\TaxLegislation\Actions\RecordTaxType;
use Mchekhashvili\TaxLegislation\Actions\RepealLaw;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotComputed;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Http\Routes;
use Mchekhashvili\TaxLegislation\Legislation\Models\Exemption;
use Mchekhashvili\TaxLegislation\Legislation\Models\TableEntry;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\ValueKind;
use Mchekhashvili\TaxLegislation\Revisions\Revision;

uses(RefreshDatabase::class);

/*
| What a person filling the forms does, and what the engine then computes. The amending law and
| its figures are invented — the mechanics are what's tested, not Georgian legal history.
*/

beforeEach(function () {
    config()->set('georgian-tax.law.source', 'database');
    $this->seed(GeorgiaLegislationSeeder::class);
    $this->refreshEngine();

    $this->law = app(RecordLegalAct::class)->handle([
        'country' => 'GE', 'kind' => 'law', 'number' => 'TEST-1', 'title' => 'Example amending law',
        'adopted_on' => '2027-06-01', 'effective_on' => '2028-01-01', 'reason' => 'Entered by hand',
    ]);
    $this->tax = fn (string $code) => TaxType::query()->ofCountry('GE')->where('code', $code)->sole();
    $this->pay = fn (int $gel, string $on, IncomeKind $kind = IncomeKind::GoodsSupply, ?Party $to = null) => new Pay(
        Party::company('buyer', Status::VatPayer), $to ?? Party::company('seller', Status::VatPayer), $kind, Money::GEL($gel), new DateTimeImmutable($on));
});

it('applies a rate amendment from its date', function () {
    app(AmendLaw::class)->handle(ValueKind::Rate, ['tax_type_id' => ($this->tax)('vat')->id, 'key' => '166', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id, 'percent' => 20]);

    expect((string) GeorgianTax::assess(($this->pay)(1000, '2027-12-31'))->vatCharged())->toBe('180.00 GEL')
        ->and((string) GeorgianTax::assess(($this->pay)(1000, '2028-01-01'))->vatCharged())->toBe('200.00 GEL');
});

it('changes nothing on a draft amendment until it is approved', function () {
    config(['tax-legislation.approval.required' => true, 'tax-legislation.approval.gate' => null]);
    $vat = fn () => (string) GeorgianTax::assess(($this->pay)(1000, '2028-06-01'))->vatCharged();

    $draft = app(AmendLaw::class)->handle(ValueKind::Rate, ['tax_type_id' => ($this->tax)('vat')->id, 'key' => '166', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id, 'percent' => 20]);
    expect($vat())->toBe('180.00 GEL');

    app(ApproveDraft::class)->handle($draft);
    expect($vat())->toBe('200.00 GEL');
});

it("reads Georgia's law and amends it through the JSON API", function () {
    config(['tax-legislation.approval.required' => true, 'tax-legislation.approval.gate' => null]);
    Routes::register(['prefix' => 'api/law']);
    $vat = ($this->tax)('vat');

    $this->getJson('/api/law/countries/GE/tax-types?on=2027-01-01')->assertOk()->assertJsonCount(6, 'data');
    $this->getJson("/api/law/tax-types/{$vat->id}/law?on=2027-01-01")->assertOk()
        ->assertJsonPath('data.tax_type.legal_basis', 'Art. 6.5.გ')
        ->assertJsonPath('data.rates.0.key', '166')
        ->assertJsonPath('data.rates.0.percent', 18);

    $draft = $this->postJson('/api/law/amendments', ['kind' => 'rate', 'tax_type_id' => $vat->id, 'key' => '166', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id, 'percent' => 20])
        ->assertAccepted()->json('data.id');
    expect((string) GeorgianTax::assess(($this->pay)(1000, '2028-06-01'))->vatCharged())->toBe('180.00 GEL');

    $this->postJson("/api/law/drafts/$draft/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    expect((string) GeorgianTax::assess(($this->pay)(1000, '2028-06-01'))->vatCharged())->toBe('200.00 GEL');
    $this->getJson("/api/law/tax-types/{$vat->id}/history/rate/166")->assertJsonPath('data.1.effective_from', '2028-01-01');
});

it('applies a threshold amendment from its date', function () {
    app(AmendLaw::class)->handle(ValueKind::Threshold, ['tax_type_id' => ($this->tax)('income')->id, 'key' => 'lottery_win', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id, 'amount' => '2000', 'currency' => 'GEL']);
    $win = fn (string $on) => GeorgianTax::assess(($this->pay)(1500, $on, IncomeKind::LotteryWin, Party::person('nino')))->for('income');

    expect($win('2027-06-01')->basis()->ref)->not->toBe('82.1.ღ')
        ->and($win('2028-06-01')->basis()->ref)->toBe('82.1.ღ');
});

it('applies an amended annual allowance from its date', function () {
    app(AmendLaw::class)->handle(ValueKind::Threshold, ['tax_type_id' => ($this->tax)('income')->id, 'key' => 'allowance', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id, 'amount' => '4000', 'currency' => 'GEL']);
    $salary = fn (string $on) => (string) GeorgianTax::assess(($this->pay)(5000, $on, IncomeKind::Salary, Party::person('mother', Relief::SingleParent)))->for('income')->base;

    expect($salary('2027-06-01'))->toBe('2000.00 GEL')    // 82.2.ა: 3,000 sheltered
        ->and($salary('2028-06-01'))->toBe('1000.00 GEL'); // 4,000 under the amendment
});

it('applies an amended import relief limit from its date', function () {
    app(AmendLaw::class)->handle(ValueKind::Threshold, ['tax_type_id' => ($this->tax)('import')->id, 'key' => 'postal_goods', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id, 'amount' => '500', 'currency' => 'GEL']);
    $parcel = fn (string $on) => GeorgianTax::assess(new Import(Party::person('nino'), Money::GEL(400), new DateTimeImmutable($on), commodity: new Commodity('3926 10 000 00'), relief: ImportRelief::PostalSmall))->for('import');

    expect($parcel('2027-06-01'))->toBeInstanceOf(Taxable::class)       // 199.დ.ვ: over 300
        ->and($parcel('2028-06-01')->basis()->ref)->toBe('199.დ.ვ');    // within 500 under the amendment
});

it('applies an amended profit tax limit from its date', function () {
    app(AmendLaw::class)->handle(ValueKind::Rate, ['tax_type_id' => ($this->tax)('profit')->id, 'key' => '98⁴.2', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id, 'percent' => 2]);
    $company = Party::company('acme');
    $books = (new StaticBooks())->set($company, 2026, revenue: Money::GEL(100_000))->set($company, 2027, revenue: Money::GEL(100_000));
    $this->app->instance(CompanyBooks::class, $books);
    $this->refreshEngine();
    $dinner = fn (string $on) => GeorgianTax::assess(new Expense($company, Money::GEL(1500), new DateTimeImmutable($on), ExpenseNature::Representation))->for('profit');

    expect($dinner('2027-06-01'))->toBeInstanceOf(Taxable::class)   // 98⁴.2: 1% of 100,000 — 500 over
        ->and($dinner('2028-06-01'))->toBeInstanceOf(NotTaxable::class); // 2% under the amendment
});

it('applies an amended temporary admission rate from its date', function () {
    app(AmendLaw::class)->handle(ValueKind::Rate, ['tax_type_id' => ($this->tax)('import')->id, 'key' => '197.4', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id, 'percent' => 4]);
    $this->refreshEngine();
    // Meat at 12% of 10,000: 1,200 on release; two calendar months
    $admitted = fn (string $on, string $until) => (string) GeorgianTax::assess(new TemporaryAdmission(
        new Import(Party::company('importer', Status::VatPayer), Money::GEL(10_000), new DateTimeImmutable($on), commodity: new Commodity('0201 30')),
        new DateTimeImmutable($until),
    ))->for('import')->amount;

    expect($admitted('2027-06-01', '2027-07-31'))->toBe('72.00 GEL')   // 197.4: 3%
        ->and($admitted('2028-06-01', '2028-07-31'))->toBe('96.00 GEL'); // 4% under the amendment, read on the registration day
});

it('refunds a re-exported car at an amended refund rate from its date, read on the export day', function () {
    app(AmendLaw::class)->handle(ValueKind::Rate, ['tax_type_id' => ($this->tax)('excise')->id, 'key' => '189.8', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id, 'percent' => 50]);
    $this->refreshEngine();
    // 2,000 cm³, under 6 years: 3,000 GEL excise on the release (188.12)
    $refund = fn (string $released, string $exported) => (string) GeorgianTax::assess(new ReexportVehicle(
        new Import(Party::company('autos', Status::VatPayer), Money::GEL(30_000), new DateTimeImmutable($released), commodity: new Commodity('8703 23', 1, engineCc: 2000, yearMade: 2025)),
        new DateTimeImmutable($exported),
    ))->for('excise')->amount;

    expect($refund('2027-09-01', '2027-12-31'))->toBe('-3000.00 GEL')   // 189.8: 100%
        ->and($refund('2027-09-01', '2028-01-02'))->toBe('-1500.00 GEL'); // 50% under the amendment
});

it('stops an exemption the law repealed', function () {
    $relief = Exemption::query()->whereBelongsTo(($this->tax)('excise'))->where('key', '194.5.ლ')->sole();
    app(RepealLaw::class)->handle($relief, ['on' => '2028-01-01', 'legal_act_id' => $this->law->id]);

    $car = fn (string $on) => GeorgianTax::assess(new Import(Party::company('autos', Status::VatPayer), Money::GEL(30_000), new DateTimeImmutable($on),
        commodity: new Commodity('8703 80', electric: true)))->for('excise');

    expect($car('2027-06-01')->basis()->ref)->toBe('194.5.ლ')
        ->and($car('2028-06-01')->basis()->ref)->not->toBe('194.5.ლ');
});

it('applies a table amendment from its date', function () {
    app(AmendLaw::class)->handle(ValueKind::TableEntry, ['tax_type_id' => ($this->tax)('excise')->id, 'key' => '2710 12', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id,
        'table' => 'excise.188.1', 'values' => ['unit' => 't', 'rate' => 650, 'description' => 'Light distillates — petrol']]);

    $petrol = fn (string $on) => (string) GeorgianTax::assess(new Import(Party::company('fuel', Status::VatPayer), Money::GEL(10_000), new DateTimeImmutable($on),
        commodity: new Commodity('2710 12', 2)))->for('excise')->amount;

    expect($petrol('2027-06-01'))->toBe('1000.00 GEL')->and($petrol('2028-06-01'))->toBe('1300.00 GEL');
});

it('applies an amendment of the figures the excise rules use from its date', function () {
    $excise = ($this->tax)('excise');
    $amend = fn (string $table, string $key, array $values) => app(AmendLaw::class)->handle(ValueKind::TableEntry, [
        'tax_type_id' => $excise->id, 'table' => $table, 'key' => $key, 'values' => $values, 'from' => '2028-01-01', 'legal_act_id' => $this->law->id]);

    // All amended before anything is assessed: the law's tables are read once a day per request.
    $amend('excise.188.cars', '188.12', ['unit' => 'cm³', 'rate' => 2.0, 'description' => 'Passenger car, 0 to 6 years']);
    $amend('excise.188.1.motorcycles', '>14', ['unit' => 'cm³', 'rate' => 3.0, 'description' => 'Motorcycle, over 14 years (ჟ)']);
    $amend('excise.188.1.tobacco', 'cigarettes', ['unit' => '20 sticks', 'rate' => 3.0, 'percent' => 25, 'description' => 'Cigarettes (2402 20)']);
    $amend('excise.188.3', 'mobile', ['unit' => 'minute', 'rate' => 0.2, 'description' => 'International call ending on a mobile network']);

    $amount = fn (Commodity $c, string $on) => (string) GeorgianTax::assess(new Import(Party::company('importer', Status::VatPayer), Money::GEL(10_000),
        new DateTimeImmutable($on), commodity: $c))->for('excise')->amount;

    // 188.12: a car up to 6 years, and the right-hand-drive triple that follows it (188.15).
    $car = new Commodity('8703 23', engineCc: 2000, yearMade: 2025);
    $rightHand = new Commodity('8703 23', engineCc: 2000, yearMade: 2025, rightHandDrive: true);
    expect($amount($car, '2027-06-01'))->toBe('3000.00 GEL')->and($amount($car, '2028-06-01'))->toBe('4000.00 GEL')
        ->and($amount($rightHand, '2027-06-01'))->toBe('9000.00 GEL')->and($amount($rightHand, '2028-06-01'))->toBe('12000.00 GEL');

    // 188.1, row 4¹: a motorcycle over 14 years.
    $bike = new Commodity('8711 20', engineCc: 250, yearMade: 2000);
    expect($amount($bike, '2027-06-01'))->toBe('600.00 GEL')->and($amount($bike, '2028-06-01'))->toBe('750.00 GEL');

    // 188.1 and 188.1¹.ა: cigarettes, both the rate per 20 and the share of the retail price.
    $cigarettes = new Commodity('2402 20', 10_000, retailPricePer20: 8);
    expect($amount($cigarettes, '2027-06-01'))->toBe('2175.00 GEL')    // 500 packs × (2.75 + 20% of 8)
        ->and($amount($cigarettes, '2028-06-01'))->toBe('2500.00 GEL'); // 500 packs × (3.00 + 25% of 8)

    // 188.3.ბ: call termination on a mobile network.
    $calls = fn (string $on) => (string) GeorgianTax::assess(new Pay(Party::company('carrier'), Party::company('telco'), IncomeKind::GoodsSupply, Money::GEL(1000),
        new DateTimeImmutable($on), commodity: new Commodity(Calls::MOBILE, 10_000)))->for('excise')->amount;
    expect($calls('2027-06-01'))->toBe('1500.00 GEL')->and($calls('2028-06-01'))->toBe('2000.00 GEL');
});

it('checks the councils\' property rates against the caps in force when the rate is fixed', function () {
    $amend = fn (string $key, array $values, string $from) => app(AmendLaw::class)->handle(ValueKind::TableEntry, [
        'tax_type_id' => ($this->tax)('property')->id, 'table' => PropertyCaps::TABLE, 'key' => $key, 'values' => $values, 'from' => $from, 'legal_act_id' => $this->law->id]);

    // 202.1 raised to 1.2% from 2028; 204.1.დ to 200% from 2 April 2028, a day after 2028's land tax is fixed (204.4).
    $amend('202.1', ['unit' => '%', 'max' => 1.2, 'description' => 'Company or organization property, up to 1.2% of value'], '2028-01-01');
    $amend('204.1.დ', ['unit' => '×', 'max' => 2.0, 'description' => 'Agricultural land, up to 200% of the base rate'], '2028-04-02');

    $tax = fn (Party $owner, PropertyItem $item, int $year) => GeorgianTax::assess(new OwnProperty($owner, $item, $year, 12))->for('property');
    $equipment = new PropertyItem(PropertyKind::Equipment, Money::GEL(100_000));
    $arable = PropertyItem::land(PropertyKind::Arable, 1, 'Marneuli');

    // No council decision: the cap in force on 31 December of the tax year (202.8).
    expect((string) $tax(Party::company('acme'), $equipment, 2027)->amount)->toBe('1000.00 GEL')
        ->and((string) $tax(Party::company('acme'), $equipment, 2028)->amount)->toBe('1200.00 GEL');

    // A council charging companies 1.1% and doubling Marneuli's arable base.
    $this->app->instance(MunicipalRates::class, new MunicipalRates(companyPercent: 1.1, landMultipliers: ['marneuli' => 2.0]));
    $this->refreshEngine();

    expect($tax(Party::company('acme'), $equipment, 2027))->toBeInstanceOf(Unresolved::class)
        ->and((string) $tax(Party::company('acme'), $equipment, 2028)->amount)->toBe('1100.00 GEL')
        ->and($tax(Party::person('nino'), $arable, 2028)->reason)->toBe("The council's 2× exceeds the 204.1.დ cap of 1.5× in force on 2028-04-01.")
        ->and((string) $tax(Party::person('nino'), $arable, 2029)->amount)->toBe('200.00 GEL');
});

it('asks how a 10 to 15 percent drink was made once rows 2 and 4 of 188¹.1 differ', function () {
    app(AmendLaw::class)->handle(ValueKind::TableEntry, ['tax_type_id' => ($this->tax)('excise')->id, 'table' => 'excise.188¹.1', 'key' => '4',
        'values' => ['unit' => 'litre', 'rate' => 6, 'description' => 'Intermediate drink'], 'from' => '2028-01-01', 'legal_act_id' => $this->law->id]);
    $duty = fn (?bool $fullyFermented, string $on = '2028-06-01') => GeorgianTax::assess(new Import(Party::company('importer', Status::VatPayer), Money::GEL(10_000),
        new DateTimeImmutable($on), commodity: new Commodity('2206 00 310', 100, alcoholPercent: 12, fullyFermented: $fullyFermented)))->for('excise');

    expect((string) $duty(null, '2027-06-01')->amount)->toBe('500.00 GEL')
        ->and($duty(null))->toBeInstanceOf(NotTaxable::class)
        ->and($duty(null)->basis()->title)->toBe('note ბ.ბ')
        ->and((string) $duty(true)->amount)->toBe('500.00 GEL')    // row 2, other fermented drink (note ბ.ბ)
        ->and((string) $duty(false)->amount)->toBe('600.00 GEL');  // row 4, intermediate drink (note გ)
});

it('drops the right-hand-drive multiplier the law repealed, leaving the base rate', function () {
    $triple = TableEntry::query()->whereBelongsTo(($this->tax)('excise'))->inTable('excise.188.cars')->where('key', '188.16')->sole();
    app(RepealLaw::class)->handle($triple, ['on' => '2028-01-01', 'legal_act_id' => $this->law->id]);

    $car = fn (string $on) => GeorgianTax::assess(new Import(Party::company('importer', Status::VatPayer), Money::GEL(10_000), new DateTimeImmutable($on),
        commodity: new Commodity('8703 23', engineCc: 2000, yearMade: 2015, rightHandDrive: true)))->for('excise');

    expect($car('2027-06-01')->basis()->ref)->toBe('188.16')->and((string) $car('2027-06-01')->amount)->toBe('27000.00 GEL')
        ->and($car('2028-06-01')->basis()->ref)->toBe('188.13')->and((string) $car('2028-06-01')->amount)->toBe('9000.00 GEL');
});

it('reports a past tax as in force but not computed', function () {
    app(RecordTaxType::class)->handle(['country' => 'GE', 'code' => 'example', 'names' => ['en' => 'Example past tax'], 'level' => 'national',
        'abolished_on' => '2008-01-01', 'abolished_by_id' => $this->law->id, 'legal_basis' => 'Art. 1']);

    expect(GeorgianTax::assess(($this->pay)(1000, '2007-06-01'))->for('example'))->toBeInstanceOf(NotComputed::class)
        ->and(GeorgianTax::assess(($this->pay)(1000, '2008-06-01'))->for('example'))->toBeNull();
});

it('skips a tax outside its recorded period', function () {
    $vat = ($this->tax)('vat');
    $vat->forceFill(['in_force_from' => '1992-01-01'])->save();

    expect(GeorgianTax::assess(($this->pay)(1000, '1991-06-01'))->for('vat'))->toBeNull()
        ->and(GeorgianTax::assess(($this->pay)(1000, '1993-06-01'))->for('vat'))->not->toBeNull();
});

it('keeps a revision of every change with its reason', function () {
    app(AmendLaw::class)->handle(ValueKind::Rate, ['tax_type_id' => ($this->tax)('vat')->id, 'key' => '166', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id,
        'percent' => 20, 'reason' => 'Example law TEST-1, Art. 1', 'source' => 'https://example.test/TEST-1']);

    $last = Revision::query()->latest('id')->first();

    expect($last->reason)->toBe('Example law TEST-1, Art. 1')
        ->and($last->source)->toBe('https://example.test/TEST-1')
        ->and(Revision::query()->where('causer_label', 'like', 'seeder:%')->exists())->toBeTrue();
});
