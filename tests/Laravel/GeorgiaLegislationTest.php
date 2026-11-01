<?php

declare(strict_types=1);

use GeorgianTax\Database\Seeders\GeorgiaLegislationSeeder;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Facades\GeorgianTax;
use GeorgianTax\Vat\Vat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TableEntry;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxRate;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\Models\Threshold;
use Mchekhashvili\TaxLegislation\Legislation\TaxLevel;
use Mchekhashvili\TaxLegislation\Rates\ConfigRateTable;
use Mchekhashvili\TaxLegislation\Rates\EloquentRateTable;
use Mchekhashvili\TaxLegislation\Rates\RateTable;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(GeorgiaLegislationSeeder::class));

it('records the Tax Code and the acts around it', function () {
    $code = LegalAct::query()->ofCountry('GE')->where('number', '3591-IIს')->sole();

    expect($code->effective_on->format('Y-m-d'))->toBe('2011-01-01')
        ->and($code->amendments->pluck('number')->all())->toBe(['6942'])
        ->and($code->citation())->toBe('Tax Code of Georgia №3591-IIს of 17.09.2010')
        ->and(LegalAct::query()->ofCountry('GE')->count())->toBe(3);
});

it('records the six taxes of Art. 6 for Georgia', function () {
    $taxes = TaxType::query()->ofCountry('GE')->current()->get()->keyBy('code');

    expect($taxes->keys()->sort()->values()->all())->toBe(['excise', 'import', 'income', 'profit', 'property', 'vat'])
        ->and($taxes['property']->level)->toBe(TaxLevel::Local)
        ->and($taxes['vat']->engine)->toBe(Vat::class)
        ->and($taxes['vat']->nameIn('ka'))->toBe('დამატებული ღირებულების გადასახადი (დღგ)')
        ->and($taxes['vat']->legal_basis)->toBe('Art. 6.5.გ');
});

it('loads every rate the package file has, under its tax', function () {
    $file = require __DIR__ . '/../../resources/law/rates.php';

    expect(TaxRate::query()->whereNotNull('percent')->count())->toBe(count($file))
        ->and(TaxType::query()->where('code', 'vat')->sole()->rateOn('166', '2026-10-30')->percent)->toBe(18.0)
        ->and(TaxType::query()->where('code', 'profit')->sole()->rateOn('98.1', '2026-10-30')->percent)->toBe(15.0)
        ->and((string) TaxType::query()->where('code', 'property')->sole()->rateOn('204.2.ა', '2026-10-30')->amount)->toBe('0.24 GEL');
});

it('gives the engine the same rates from the database as from the file', function () {
    $file = ConfigRateTable::standard(__DIR__ . '/../../resources/law/rates.php');
    $db = new EloquentRateTable('GE');
    $day = new DateTimeImmutable('2026-10-30');

    foreach (array_keys(require __DIR__ . '/../../resources/law/rates.php') as $article) {
        expect($db->rate((string) $article, $day)->percent)->toBe($file->rate((string) $article, $day)->percent, "Art. $article");
    }
});

it('loads the excise, import and land tables', function () {
    $excise = TaxType::query()->where('code', 'excise')->sole()->tableOn('excise.188.1', '2026-10-30')->keyBy('key');
    $import = TaxType::query()->where('code', 'import')->sole();

    expect($excise['2710 12']->values['rate'])->toBe(500)
        ->and($import->tableOn('import.197.1', '2026-10-30'))->toHaveCount(176)
        ->and($import->tableOn('import.197.2', '2026-10-30'))->toHaveCount(37)
        ->and(TableEntry::query()->inTable('property.204.1.arable')->where('key', 'marneuli')->sole()->values['gel_per_ha'])->toBe(100);
});

it('records the thresholds the engine draws lines at', function () {
    $vat = TaxType::query()->where('code', 'vat')->sole()->thresholdOn('registration', '2026-10-30');

    expect((string) $vat->amount)->toBe('100000.00 GEL')
        ->and($vat->article)->toBe('165.1')
        ->and(Threshold::query()->count())->toBe(5);
});

it('reseeds without duplicating', function () {
    $this->seed(GeorgiaLegislationSeeder::class);

    expect(TaxType::query()->count())->toBe(6)->and(LegalAct::query()->count())->toBe(3);
});

it('runs the engine on rates from the database', function () {
    config()->set('georgian-tax.law.source', 'database');
    app()->forgetInstance(RateTable::class);
    $this->refreshEngine();

    expect(app(RateTable::class))->toBeInstanceOf(EloquentRateTable::class);

    $sale = new Pay(Party::company('buyer', Status::VatPayer), Party::company('seller', Status::VatPayer), IncomeKind::GoodsSupply, Mchekhashvili\Money\Money::GEL(1000), new DateTimeImmutable('2026-10-30'));
    expect((string) GeorgianTax::assess($sale)->vatCharged())->toBe('180.00 GEL');

    // An amendment recorded in the database takes effect on its date, no code change needed.
    $vat = TaxType::query()->where('code', 'vat')->sole();
    TaxRate::amend($vat, '166', ['percent' => 20], from: '2027-01-01');

    $later = new Pay(Party::company('buyer', Status::VatPayer), Party::company('seller', Status::VatPayer), IncomeKind::GoodsSupply, Mchekhashvili\Money\Money::GEL(1000), new DateTimeImmutable('2027-02-01'));
    expect((string) GeorgianTax::assess($sale)->vatCharged())->toBe('180.00 GEL')
        ->and((string) GeorgianTax::assess($later)->vatCharged())->toBe('200.00 GEL');
});
