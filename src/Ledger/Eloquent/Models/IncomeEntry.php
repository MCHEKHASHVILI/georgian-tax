<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger\Eloquent\Models;

use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Domain\Income\IncomeKind;
use Illuminate\Database\Eloquent\Builder;
use Mchekhashvili\Money\Laravel\MoneyCast;

/**
 * Income one party received from another — the history behind the annual thresholds.
 *
 * @property string $recipient
 * @property ?string $payer
 * @property string $kind     IncomeKind case name
 * @property int $year
 * @property ?string $heir    HeirOrder case name
 */
final class IncomeEntry extends Model
{
    protected function baseTable(): string { return 'income_entries'; }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'occurred_on' => 'immutable_date',
            'income' => MoneyCast::in('GEL'),
            'taxable_base' => MoneyCast::in('GEL'),
        ];
    }

    /** @param Builder<self> $query */
    public function scopeOfKinds(Builder $query, IncomeKind ...$kinds): void
    {
        $query->when($kinds !== [], fn (Builder $q) => $q->whereIn('kind', array_map(fn (IncomeKind $k) => $k->name, $kinds)));
    }

    /** @param Builder<self> $query */
    public function scopeFromHeirs(Builder $query, HeirOrder ...$orders): void
    {
        $query->whereIn('heir', array_map(fn (HeirOrder $o) => $o->name, $orders));
    }
}
