<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger\Eloquent\Models;

use GeorgianTax\Database\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mchekhashvili\Money\Laravel\MoneyCast;

/**
 * What one tax said about an assessed event: the outcome type, the article, and for a charge the
 * base, rate or per-unit duty, amount, who pays and by when.
 *
 * @property string $tax
 * @property string $outcome   taxable, levied, exempt, exempt_with_credit, not_taxable, unresolved
 * @property string $article
 */
final class AssessedOutcome extends Model
{
    protected function baseTable(): string { return 'outcomes'; }

    protected function casts(): array
    {
        return [
            'base' => MoneyCast::in('GEL'),
            'amount' => MoneyCast::in('GEL'),
            'rate' => 'float',
            'quantity' => 'float',
            'due_on' => 'immutable_date',
            'notes' => 'array',
        ];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }
}
