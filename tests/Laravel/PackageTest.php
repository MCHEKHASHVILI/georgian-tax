<?php

declare(strict_types=1);

use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\TaxEngine;
use GeorgianTax\Excise\ExciseTable;
use GeorgianTax\Facades\GeorgianTax;
use Mchekhashvili\TaxLegislation\Law\Citation;
use GeorgianTax\PropertyTax\MunicipalRates;
use GeorgianTax\PropertyTax\PropertyCaps;
use Mchekhashvili\TaxLegislation\Rates\ConfigRateTable;
use Mchekhashvili\TaxLegislation\Rates\ConfigThresholdTable;
use Mchekhashvili\TaxLegislation\Rates\CountryTables;
use GeorgianTax\Vat\Supply;
use Mchekhashvili\Money\Money;

function serviceFromGermany(): Pay
{
    return new Pay(Party::company('acme'), Party::foreignCompany('gmbh', 'DE'), IncomeKind::ServiceFee,
        Money::GEL(1000), new DateTimeImmutable('2026-10-30'), Place::abroad('DE'));
}

/** A stand-in VAT stage, to show an app can rewire a tax from config. */
final class ZeroVatForTests
{
    public function handle(Supply $supply, Closure $next): Outcome
    {
        return new NotTaxable('vat', new Citation('test'), 'Replaced by config.');
    }
}

it('registers the engine with the six taxes in configured order', function () {
    expect(app(TaxEngine::class)->taxes()->keys()->all())
        ->toBe(['import', 'excise', 'vat', 'income', 'profit', 'property']);
});

it('answers through the facade', function () {
    $report = GeorgianTax::process(serviceFromGermany());

    expect((string) $report->for('income'))->toContain('134.1.ე')
        ->and((string) $report->vatCharged())->toBe('0.00 GEL')
        ->and((string) $report->netToRecipient())->toBe('900.00 GEL')
        ->and($report->toArray()['total_tax'])->toBe('280.00 GEL');
});

it('narrows to some taxes', function () {
    expect(GeorgianTax::only('vat', 'income')->taxes()->keys()->all())->toBe(['vat', 'income']);

    GeorgianTax::only('vat', 'sales-tax');
})->throws(InvalidArgumentException::class, 'Unknown tax: sales-tax');

it('quotes without recording', function () {
    $ledger = GeorgianTax::ledger();
    $since = new DateTimeImmutable('2026-01-01');

    GeorgianTax::assess(serviceFromGermany());
    expect($ledger->received(Party::foreignCompany('gmbh', 'DE'), 2026)->isZero())->toBeTrue();

    GeorgianTax::process(serviceFromGermany());
    expect((string) $ledger->received(Party::foreignCompany('gmbh', 'DE'), 2026))->toBe('1000.00 GEL');
});

it('lets the app replace a tax\'s stages from config', function () {
    config()->set('georgian-tax.pipelines.vat', [ZeroVatForTests::class]);
    $this->refreshEngine();

    expect(GeorgianTax::process(serviceFromGermany())->for('vat')->basis()->ref)->toBe('test');
});

it('lets the app add or reorder taxes from config', function () {
    config()->set('georgian-tax.taxes', [\GeorgianTax\IncomeTax\IncomeTax::class]);
    $this->refreshEngine();

    expect(GeorgianTax::taxes()->keys()->all())->toBe(['income']);
});

it('keeps Georgia\'s law apart from another country\'s package in the same app', function () {
    // An invented second country whose tables use the same article keys as Georgia's.
    app(CountryTables::class)->register('AM',
        rates: new ConfigRateTable(['166' => ['2000-01-01' => 20]]),
        thresholds: new ConfigThresholdTable(['vat' => ['registration' => ['1', ['' => 1]]]], 'AMD'),
    );
    $this->refreshEngine();

    $sale = new Pay(Party::company('buyer', Status::VatPayer), Party::company('seller', Status::VatPayer), IncomeKind::GoodsSupply, Money::GEL(1000), new DateTimeImmutable('2026-10-30'));

    expect((string) GeorgianTax::assess($sale)->vatCharged())->toBe('180.00 GEL')   // Georgia's Art. 166, not the other country's
        ->and(app(CountryTables::class)->rates('AM')->rate('166', new DateTimeImmutable('2026-10-30'))->percent)->toBe(20.0)
        ->and((string) app(CountryTables::class)->thresholds('GE')->threshold('vat', 'registration', new DateTimeImmutable('2026-10-30')))->toBe('100000.00 GEL');
});

it('reads the law tables once, from the package or an overriding file', function () {
    expect(app(CountryTables::class)->rates('GE'))->toBe(app(CountryTables::class)->rates('GE'))
        ->and(app(ExciseTable::class)->find(new \GeorgianTax\Domain\Goods\Commodity('2710 12'))['rate'])->toBe(500.0);

    $file = tempnam(sys_get_temp_dir(), 'excise') . '.php';
    file_put_contents($file, "<?php return ['2710 12' => ['t', 650, 'Petrol, amended']];");
    config()->set('georgian-tax.law.excise', $file);
    app()->forgetInstance(ExciseTable::class);

    expect(app(ExciseTable::class)->find(new \GeorgianTax\Domain\Goods\Commodity('2710 12'))['rate'])->toBe(650.0);
});

it('builds the councils\' property rates from config, null taking the cap', function () {
    expect(app(MunicipalRates::class)->companyPercent)->toBeNull();

    config()->set('georgian-tax.property.company_percent', 0.5);
    config()->set('georgian-tax.property.land_multipliers', ['Marneuli' => 1.5]);
    app()->forgetInstance(MunicipalRates::class);

    $rates = app(MunicipalRates::class);
    expect($rates->companyPercent)->toBe(0.5)->and($rates->landMultiplier('marneuli'))->toBe(1.5)->and($rates->landMultiplier('gori'))->toBeNull();

    config()->set('georgian-tax.property.company_percent', -1);
    app()->forgetInstance(MunicipalRates::class);
    app(MunicipalRates::class);
})->throws(InvalidArgumentException::class);

it('reads the caps on the councils\' rates from the law files', function () {
    expect(app(PropertyCaps::class)->max('202.1'))->toBe(1.0)
        ->and(app(PropertyCaps::class)->min('202.5'))->toBe(0.8)
        ->and(app(PropertyCaps::class)->max('204.1.დ'))->toBe(1.5);

    // A published property.php from before the caps keeps its land rates and takes the package's caps.
    $file = tempnam(sys_get_temp_dir(), 'land') . '.php';
    file_put_contents($file, '<?php return ' . var_export(array_diff_key(require __DIR__ . '/../../resources/law/property.php', ['caps' => true]), true) . ';');
    config()->set('georgian-tax.law.land', $file);
    app()->forgetInstance(PropertyCaps::class);

    expect(app(PropertyCaps::class)->max('202.3¹'))->toBe(0.6);
});

it('publishes config, migrations and the law tables', function () {
    $paths = \GeorgianTax\GeorgianTaxServiceProvider::pathsToPublish(\GeorgianTax\GeorgianTaxServiceProvider::class);

    expect(array_keys($paths))->toHaveCount(3)
        ->and(\GeorgianTax\GeorgianTaxServiceProvider::publishableGroups())->toContain('georgian-tax-config', 'georgian-tax-migrations', 'georgian-tax-law');
});
