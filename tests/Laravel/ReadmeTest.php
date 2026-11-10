<?php

declare(strict_types=1);

use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\OwnProperty;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Domain\Property\PropertyItem;
use GeorgianTax\Domain\Property\PropertyKind;
use GeorgianTax\Facades\GeorgianTax;
use GeorgianTax\GeorgianTaxServiceProvider;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Facades\Taxes;

/*
| The examples in README.md, run as written, so the README does not drift from the code.
| A failure here means the README needs the same change as the code.
*/

it('pays a foreign service fee as the README says', function () {
    $report = GeorgianTax::process(new Pay(
        payer:  Party::company('acme'),
        payee:  Party::foreignCompany('gmbh', 'DE'),
        for:    IncomeKind::ServiceFee,
        amount: Money::GEL(1000),
        on:     new DateTimeImmutable('2026-10-30'),
        place:  Place::abroad('DE'),
    ));

    expect((string) $report->for('income'))->toBe("Art. 134.1.ე — Other Georgian-source income of a non-resident: 10% of 1000.00 GEL = 100.00 GEL, withheld by acme, due 2026-11-15")
        ->and((string) $report->for('vat'))->toBe('Art. 161.1.ა — Reverse charge: 18% of 1000.00 GEL = 180.00 GEL, reverse-charged by acme, due 2026-11-15')
        ->and((string) $report->netToRecipient())->toBe('900.00 GEL')
        ->and($report->outcomes->keys()->all())->toBe(['import', 'excise', 'vat', 'income', 'profit', 'property']);
});

it('imports a car through three taxes as the README says', function () {
    $report = GeorgianTax::process(new Import(
        importer:     Party::company('autos', Status::VatPayer),
        customsValue: Money::GEL(20_000),
        on:           new DateTimeImmutable('2026-10-30'),
        commodity:    new Commodity('8703 23', engineCc: 2000, yearMade: 2019),
    ));

    expect((string) $report->for('import'))->toStartWith('Art. 197.6 — Passenger car import tax: 2000 cm³ × 0.05 GEL = 135.00 GEL')
        ->and((string) $report->for('excise'))->toStartWith('Art. 188.13 — Passenger car: 2000 cm³ × 4.50 GEL = 9000.00 GEL')
        ->and((string) $report->for('vat'))->toStartWith('Art. 165².2.ა — VAT on import: 18% of 29135.00 GEL = 5244.30 GEL');
});

it('taxes company property as the README says', function () {
    $report = GeorgianTax::process(new OwnProperty(Party::company('acme'), new PropertyItem(PropertyKind::Building, Money::GEL(100_000), acquiredYear: 1998), 2026));

    expect((string) $report->for('property'))->toStartWith('Art. 202.1 — Company property: 1% of 300000.00 GEL = 3000.00 GEL, paid by acme, due 2027-04-01');
});

it('gives Georgia\'s engine the ways the README names', function () {
    expect(app(GeorgianTaxServiceProvider::ENGINE)->taxes()->keys()->all())
        ->toBe(GeorgianTax::taxes()->keys()->all())
        ->and(Taxes::forCountry('GE')->taxes()->keys()->all())->toBe(GeorgianTax::taxes()->keys()->all());
});
