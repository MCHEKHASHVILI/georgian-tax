<?php

declare(strict_types=1);

namespace GeorgianTax\Legislation\Models;

use DateTimeInterface;
use GeorgianTax\Database\Model;
use GeorgianTax\Engine\Tax;
use GeorgianTax\Legislation\TaxLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One kind of tax for as long as it existed — VAT, the social tax, a turnover tax.
 *
 * Taxes come and go. Each row is one tax over one period: in force from `in_force_from` until the
 * day before `abolished_on`. Open-ended dates mean "still in force" (`abolished_on` null) and "since
 * before the history recorded here" (`in_force_from` null). A tax that is abolished and later
 * brought back gets a new row with the same code. A tax that took over from another names it in
 * `replaces_id`: VAT replacing a turnover tax, or a tax renamed by a new code.
 *
 * `engine` names the GeorgianTax\Engine\Tax class that computes the tax, when the package has one.
 *
 * @property int $id
 * @property string $code
 * @property string $name_ka
 * @property string $name_en
 * @property TaxLevel $level
 * @property ?\Carbon\CarbonImmutable $in_force_from
 * @property ?\Carbon\CarbonImmutable $abolished_on
 * @property ?int $replaces_id
 * @property ?class-string<Tax> $engine
 * @property ?string $legal_basis      where the law establishes it: 'Tax Code of Georgia (№3591-IIს), Art. 6.5.ა'
 * @property ?string $introduced_by    the act that brought it in
 * @property ?string $abolished_by     the act that removed it
 * @property ?string $notes
 */
final class TaxType extends Model
{
    protected function baseTable(): string
    {
        return 'tax_types';
    }

    protected function casts(): array
    {
        return [
            'level' => TaxLevel::class,
            'in_force_from' => 'immutable_date',
            'abolished_on' => 'immutable_date',
        ];
    }

    /* ── Relations ── */

    /** @return BelongsTo<self, $this> the tax this one took over from */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    /** @return HasMany<self, $this> the taxes that took over from this one */
    public function replacedBy(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_id');
    }

    /* ── Scopes ── */

    /** Taxes in force on a day. @param Builder<self> $query */
    public function scopeInForceOn(Builder $query, DateTimeInterface|string $day): void
    {
        $day = $day instanceof DateTimeInterface ? $day->format('Y-m-d') : $day;

        $query
            ->where(fn (Builder $q) => $q->whereNull('in_force_from')->orWhereDate('in_force_from', '<=', $day))
            ->where(fn (Builder $q) => $q->whereNull('abolished_on')->orWhereDate('abolished_on', '>', $day));
    }

    /** @param Builder<self> $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->inForceOn(now());
    }

    /** @param Builder<self> $query */
    public function scopeOfLevel(Builder $query, TaxLevel $level): void
    {
        $query->where('level', $level);
    }

    /** Every period a tax code has existed, oldest first. @param Builder<self> $query */
    public function scopeHistoryOf(Builder $query, string $code): void
    {
        $query->where('code', $code)->orderByRaw('in_force_from is not null')->orderBy('in_force_from');
    }

    /* ── Questions ── */

    public function isInForceOn(DateTimeInterface $day): bool
    {
        $d = $day->format('Y-m-d');

        return ($this->in_force_from === null || $this->in_force_from->format('Y-m-d') <= $d)
            && ($this->abolished_on === null || $this->abolished_on->format('Y-m-d') > $d);
    }

    public function isAbolished(): bool
    {
        return $this->abolished_on !== null && $this->abolished_on->isPast();
    }

    /** The chain of predecessors, nearest first: VAT → turnover tax → … */
    public function predecessors(): Collection
    {
        $chain = new Collection();

        for ($tax = $this->replaces; $tax !== null && !$chain->contains($tax); $tax = $tax->replaces) {
            $chain->push($tax);
        }

        return $chain;
    }

    /** Abolish the tax from a day, citing the act. */
    public function abolish(DateTimeInterface|string $on, ?string $by = null): self
    {
        $this->forceFill(['abolished_on' => $on, 'abolished_by' => $by])->save();

        return $this;
    }
}
