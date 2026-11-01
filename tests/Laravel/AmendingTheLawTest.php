<?php

declare(strict_types=1);

use GeorgianTax\Database\Seeders\GeorgiaLegislationSeeder;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Facades\GeorgianTax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Actions\AmendLaw;
use Mchekhashvili\TaxLegislation\Actions\RecordLegalAct;
use Mchekhashvili\TaxLegislation\Actions\RecordTaxType;
use Mchekhashvili\TaxLegislation\Actions\RepealLaw;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotComputed;
use Mchekhashvili\TaxLegislation\Legislation\Models\Exemption;
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

it('applies a threshold amendment from its date', function () {
    app(AmendLaw::class)->handle(ValueKind::Threshold, ['tax_type_id' => ($this->tax)('income')->id, 'key' => 'lottery_win', 'from' => '2028-01-01', 'legal_act_id' => $this->law->id, 'amount' => '2000', 'currency' => 'GEL']);
    $win = fn (string $on) => GeorgianTax::assess(($this->pay)(1500, $on, IncomeKind::LotteryWin, Party::person('nino')))->for('income');

    expect($win('2027-06-01')->basis()->ref)->not->toBe('82.1.ღ')
        ->and($win('2028-06-01')->basis()->ref)->toBe('82.1.ღ');
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
