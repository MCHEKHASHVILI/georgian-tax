<?php

declare(strict_types=1);

namespace GeorgianTax\Engine;

use Mchekhashvili\TaxLegislation\Engine\Tax as CountryTax;

/**
 * A tax of Georgian law: paid in lari, its stages replaceable under config('georgian-tax.pipelines').
 * The engine itself — pipelines, outcomes, reports — comes from mchekhashvili/tax-legislation.
 */
abstract class Tax extends CountryTax
{
    public const COUNTRY = 'GE';

    public function country(): string
    {
        return self::COUNTRY;
    }

    public function currency(): string
    {
        return 'GEL';
    }

    protected function configKey(): string
    {
        return 'georgian-tax';
    }
}
