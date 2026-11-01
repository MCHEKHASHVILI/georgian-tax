<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger\Eloquent\Models;

use GeorgianTax\Database\Model;
use Mchekhashvili\Money\Laravel\MoneyCast;

/** What a company spent in a year on a category profit tax limits: 'charity', '98⁴' representation. */
final class SpendingEntry extends Model
{
    protected function baseTable(): string { return 'spending_entries'; }

    protected function casts(): array
    {
        return ['year' => 'integer', 'amount' => MoneyCast::in('GEL')];
    }
}
