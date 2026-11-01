<?php

declare(strict_types=1);

use GeorgianTax\Database\Seeders\TaxTypeSeeder;
use GeorgianTax\Legislation\Models\TaxType;
use GeorgianTax\Legislation\TaxLevel;
use GeorgianTax\Vat\Vat;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| The dates and acts below that are not in the seeder are invented for the tests — they exercise
| the model, they are not a record of Georgian tax history.
*/

beforeEach(fn () => $this->seed(TaxTypeSeeder::class));

it('seeds the six taxes Art. 6 lists today', function () {
    expect(TaxType::query()->current()->pluck('code')->sort()->values()->all())
        ->toBe(['excise', 'import', 'income', 'profit', 'property', 'vat'])
        ->and(TaxType::query()->ofLevel(TaxLevel::Local)->pluck('code')->all())->toBe(['property'])
        ->and(TaxType::query()->where('code', 'vat')->sole()->engine)->toBe(Vat::class)
        ->and(TaxType::query()->where('code', 'vat')->sole()->legal_basis)->toBe('Tax Code of Georgia (№3591-IIს), Art. 6.5.გ');
});

it('seeds idempotently', function () {
    $this->seed(TaxTypeSeeder::class);

    expect(TaxType::query()->count())->toBe(6);
});

it('knows which taxes were in force on a day', function () {
    TaxType::query()->create([
        'code' => 'social', 'name_ka' => 'სოციალური გადასახადი', 'name_en' => 'Social tax', 'level' => TaxLevel::National,
        'abolished_on' => '2008-01-01', 'abolished_by' => 'Example act №1',
    ]);

    expect(TaxType::query()->inForceOn('2007-12-31')->pluck('code'))->toContain('social')
        ->and(TaxType::query()->inForceOn('2008-01-01')->pluck('code'))->not->toContain('social')
        ->and(TaxType::query()->where('code', 'social')->sole()->isAbolished())->toBeTrue()
        ->and(TaxType::query()->where('code', 'social')->sole()->isInForceOn(new DateTimeImmutable('2007-06-01')))->toBeTrue();
});

it('links a tax to the one it replaced', function () {
    $turnover = TaxType::query()->create([
        'code' => 'turnover', 'name_ka' => 'ბრუნვის გადასახადი', 'name_en' => 'Turnover tax', 'level' => TaxLevel::National,
        'in_force_from' => '1990-01-01', 'abolished_on' => '1992-03-01',
    ]);
    $vat = TaxType::query()->where('code', 'vat')->sole();
    $vat->replaces()->associate($turnover)->save();

    expect($vat->fresh()->replaces->code)->toBe('turnover')
        ->and($turnover->replacedBy->pluck('code')->all())->toBe(['vat'])
        ->and($vat->fresh()->predecessors()->pluck('code')->all())->toBe(['turnover']);
});

it('keeps every period of a tax that was abolished and brought back', function () {
    $first = TaxType::query()->create([
        'code' => 'example', 'name_ka' => 'მაგალითი', 'name_en' => 'Example', 'level' => TaxLevel::Local,
        'in_force_from' => '2001-01-01',
    ]);
    $first->abolish('2005-01-01', 'Example act №2');

    TaxType::query()->create([
        'code' => 'example', 'name_ka' => 'მაგალითი', 'name_en' => 'Example', 'level' => TaxLevel::Local,
        'in_force_from' => '2012-01-01', 'replaces_id' => $first->id, 'introduced_by' => 'Example act №3',
    ]);

    $history = TaxType::query()->historyOf('example')->get();

    expect($history->map(fn (TaxType $t) => [$t->in_force_from?->format('Y'), $t->abolished_on?->format('Y')])->all())
        ->toBe([['2001', '2005'], ['2012', null]])
        ->and(TaxType::query()->inForceOn('2008-06-01')->where('code', 'example')->exists())->toBeFalse()
        ->and(TaxType::query()->inForceOn('2020-06-01')->where('code', 'example')->count())->toBe(1);
});

it('refuses two periods of one tax starting the same day', function () {
    $row = ['code' => 'x', 'name_ka' => 'x', 'name_en' => 'x', 'level' => TaxLevel::National, 'in_force_from' => '2020-01-01'];
    TaxType::query()->create($row);
    TaxType::query()->create($row);
})->throws(Illuminate\Database\UniqueConstraintViolationException::class);
