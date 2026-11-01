<?php

declare(strict_types=1);

namespace GeorgianTax\Facades;

use GeorgianTax\Engine\TaxEngine;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \GeorgianTax\Engine\TaxReport process(\GeorgianTax\Domain\Event\TaxableEvent $event)
 * @method static \GeorgianTax\Engine\TaxReport assess(\GeorgianTax\Domain\Event\TaxableEvent $event)
 * @method static mixed queue(\GeorgianTax\Domain\Event\TaxableEvent $event)
 * @method static TaxEngine only(string ...$names)
 * @method static \GeorgianTax\Engine\Tax tax(string $name)
 * @method static \Illuminate\Support\Collection<string, \GeorgianTax\Engine\Tax> taxes()
 * @method static \GeorgianTax\Ledger\YearToDate ledger()
 *
 * @see TaxEngine
 */
final class GeorgianTax extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TaxEngine::class;
    }
}
