<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger\Eloquent\Models;

use Mchekhashvili\Money\Laravel\MoneyCast;

/**
 * A company's closed year as profit tax limits need it: revenue (98⁴.2) and net profit (98³.3.ა),
 * and the year it was founded (98⁴.3). Fill it from your accounting.
 */
final class CompanyYear extends Model
{
    protected function baseTable(): string { return 'company_years'; }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'founded_in' => 'integer',
            'revenue' => MoneyCast::in('GEL'),
            'net_profit' => MoneyCast::in('GEL'),
        ];
    }
}
