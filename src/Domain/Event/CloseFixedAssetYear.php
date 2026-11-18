<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Party\Party;
use Mchekhashvili\Money\Money;

/**
 * A calendar year's end for a fixed asset in mixed use (177.5–6), in the year's last reporting
 * period. Each year from the one it was put into use, for 10 years (real estate) or 5 (other fixed
 * assets), a tenth or a fifth of its VAT is taken (177.6) and:
 *
 *  - when the VAT was credited in full at once (`$creditedInFull`, 177.5.ა), the part of it in the
 *    year's share of operations without the right to credit is given back — a positive `Levied`;
 *  - otherwise the part of it in the year's share of operations with the right to credit is
 *    credited (177.5.ბ) — a negative `Levied`.
 *
 * `$turnover` is the year's turnover, the total being the year's supplies of goods and services
 * without VAT (177.5.ა), the caller's figures.
 */
final readonly class CloseFixedAssetYear implements TaxableEvent
{
    public function __construct(
        public Party $taxpayer,
        public int $year,
        public FixedAsset $asset,
        /** The year's turnover (177.5). */
        public Turnover $turnover,
        /** Whether the asset's VAT was credited in full in its first reporting period (177.5.ა). */
        public bool $creditedInFull,
    ) {}

    public function recipient(): Party { return $this->taxpayer; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    public function date(): DateTimeImmutable { return new DateTimeImmutable("{$this->year}-12-31"); }
    public function place(): Place { return Place::georgia(); }
}
