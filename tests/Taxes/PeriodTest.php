<?php

declare(strict_types=1);

use GeorgianTax\Domain\Period;
use GeorgianTax\Engine\Periods;

test('a period reads as the Code says it and counts from the next day', function () {
    expect(Period::days(180)->describe())->toBe('180 calendar days')
        ->and((string) Period::days(180))->toBe('180 days')
        ->and((string) new Period(1, 'years'))->toBe('1 year')
        ->and(new Period(6, 'months')->describe())->toBe('6 months')
        ->and(Period::days(180)->after(new DateTimeImmutable('2026-10-30 15:00'))->format('Y-m-d H:i'))->toBe('2027-04-28 00:00');
});

test('a period has a known unit and is not negative', function () {
    expect(fn () => new Period(-1, 'days'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Period(3, 'weeks'))->toThrow(InvalidArgumentException::class, 'weeks');
});

test('the law file gives each tax its periods', function () {
    $periods = Periods::standard();

    expect((string) $periods->of('income', '82.1.ვ.ა'))->toBe('2 years')
        ->and((string) $periods->of('income', '82.1.ვ.ბ'))->toBe('6 months')
        ->and((string) $periods->of('excise', '189.8'))->toBe('180 days')
        ->and((string) $periods->of('import', '198.3'))->toBe('180 days')
        ->and($periods->of('import', '189.8'))->toBeNull()
        ->and($periods->of('vat', '177.6'))->toBeNull();
});
