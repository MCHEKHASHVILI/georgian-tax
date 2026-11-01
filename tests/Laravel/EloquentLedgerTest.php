<?php

declare(strict_types=1);

use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Facades\GeorgianTax;
use GeorgianTax\Ledger\CompanyBooks;
use GeorgianTax\Ledger\Eloquent\EloquentBooks;
use GeorgianTax\Ledger\Eloquent\EloquentLedger;
use Mchekhashvili\TaxLegislation\Assessments\Models\AssessedOutcome;
use Mchekhashvili\TaxLegislation\Assessments\Models\Assessment;
use GeorgianTax\Ledger\Eloquent\Models\IncomeEntry;
use GeorgianTax\Ledger\YearToDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mchekhashvili\Money\Money;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('georgian-tax.ledger', 'eloquent');
    config()->set('tax-legislation.store_reports', true);
    app()->forgetInstance(YearToDate::class);
    $this->refreshEngine();
    app()->forgetInstance(CompanyBooks::class);
    // The listener is registered at boot from config; register it for these tests.
    app('events')->listen(\Mchekhashvili\TaxLegislation\Events\TaxableEventAssessed::class, \Mchekhashvili\TaxLegislation\Listeners\StoreAssessment::class);

    $this->today = new DateTimeImmutable('2026-10-30');
});

it('uses the Eloquent ledger and books', function () {
    expect(app(YearToDate::class))->toBeInstanceOf(EloquentLedger::class)
        ->and(app(CompanyBooks::class))->toBeInstanceOf(EloquentBooks::class);
});

it('creates prefixed tables', function () {
    expect(Schema::hasTable('georgian_tax_income_entries'))->toBeTrue()
        ->and(Schema::hasTable('tax_assessment_outcomes'))->toBeTrue();
});

it('records income and reads year-to-date totals from the database', function () {
    $acme = Party::company('acme');
    $nino = Party::person('nino');

    GeorgianTax::process(new Pay($acme, $nino, IncomeKind::Salary, Money::GEL(3000), $this->today));
    GeorgianTax::process(new Pay($acme, $nino, IncomeKind::Salary, Money::GEL(2000), $this->today));

    $ledger = GeorgianTax::ledger();
    $row = IncomeEntry::query()->first();

    expect((string) $ledger->received($nino, 2026, IncomeKind::Salary))->toBe('5000.00 GEL')
        ->and((string) $ledger->paid($acme, 2026))->toBe('5000.00 GEL')
        ->and($ledger->received($nino, 2025)->isZero())->toBeTrue()
        ->and((string) $ledger->taxableIncome($nino, 2026))->toBe('5000.00 GEL')
        ->and($row->getAttributes()['income'])->toBe(300000)
        ->and($row->kind)->toBe('Salary');
});

it('carries the VAT threshold across events through the database', function () {
    $shop = Party::company('shop');
    $buyer = Party::company('buyer', Status::VatPayer);
    $ledger = GeorgianTax::ledger();

    $ledger->seedTurnover($shop, 'vat', new DateTimeImmutable('2026-03-01'), Money::GEL(60_000));
    $first = GeorgianTax::process(new Pay($buyer, $shop, IncomeKind::GoodsSupply, Money::GEL(30_000), $this->today));
    $second = GeorgianTax::process(new Pay($buyer, $shop, IncomeKind::GoodsSupply, Money::GEL(20_000), $this->today));

    expect($first->for('vat')->basis()->ref)->toBe('165.1')
        ->and($first->vatCharged()->isZero())->toBeTrue()
        ->and((string) $second->vatCharged())->toBe('3600.00 GEL')
        ->and((string) $ledger->turnover($shop, 'vat', new DateTimeImmutable('2025-10-30')))->toBe('110000.00 GEL');
});

it('keeps company figures for the profit tax limits', function () {
    $acme = Party::company('acme');
    app(EloquentBooks::class)->close($acme, 2025, revenue: Money::GEL(1_000_000), netProfit: Money::GEL(50_000), foundedIn: 2010);
    $charity = new Party('red-cross', PartyKind::Organization, statuses: [Status::CharityOrganization]);

    $within = GeorgianTax::process(new Gift($acme, $charity, Money::GEL(4000), $this->today));
    $beyond = GeorgianTax::process(new Gift($acme, $charity, Money::GEL(2000), $this->today));

    expect($within->for('profit')->basis()->ref)->toBe('98³.3.ა')
        ->and($beyond->for('profit')->basis()->ref)->toBe('98³')
        ->and(app(CompanyBooks::class)->foundedIn($acme))->toBe(2010);
});

it('stores every report with its outcomes', function () {
    $import = new Import(Party::company('autos', Status::VatPayer), Money::GEL(20_000), $this->today,
        commodity: new Commodity('8703 23', engineCc: 2000, yearMade: 2019));

    GeorgianTax::process($import);

    $assessment = Assessment::query()->with('outcomes')->sole();
    $byTax = $assessment->outcomes->keyBy('tax');

    expect($assessment->event_type)->toBe(Import::class)
        ->and($assessment->event())->toEqual($import)
        ->and((string) $assessment->total_tax)->toBe('14379.30 GEL')
        ->and($assessment->outcomes)->toHaveCount(6)
        ->and($byTax['import']->outcome)->toBe('levied')
        ->and($byTax['import']->unit)->toBe('cm³')
        ->and($byTax['vat']->outcome)->toBe('taxable')
        ->and($byTax['vat']->rate)->toBe(18.0)
        ->and((string) $byTax['vat']->base)->toBe('29135.00 GEL')
        ->and($byTax['excise']->article)->toBe('188.13')
        ->and($byTax['property']->outcome)->toBe('not_taxable');

    expect(AssessedOutcome::query()->where('tax', 'excise')->sole()->amount)->toEqual(Money::GEL(9000));
});

it('does not store quotes', function () {
    GeorgianTax::assess(new Pay(Party::company('a'), Party::person('b'), IncomeKind::Salary, Money::GEL(100), $this->today));

    expect(Assessment::query()->count())->toBe(0)->and(IncomeEntry::query()->count())->toBe(0);
});
