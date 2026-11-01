<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger\Eloquent\Models;

use Mchekhashvili\Money\Laravel\MoneyCast;

/** Turnover a tax counted for a party on a day — VAT's rolling 12 months (165.1). */
final class TurnoverEntry extends Model
{
    protected function baseTable(): string { return 'turnover_entries'; }

    protected function casts(): array
    {
        return ['occurred_on' => 'immutable_date', 'amount' => MoneyCast::in('GEL')];
    }
}
