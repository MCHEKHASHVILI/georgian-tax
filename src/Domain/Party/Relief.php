<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Party;

/** Art. 82.2 — personal annual allowances. Only the highest one applies (82.3). */
enum Relief
{
    case Veteran;
    case MotherOfGeorgia;
    case SingleParent;
    case AdoptiveParent;          // first year after adoption
    case FosterParent;
    case DisabledSinceChildhood;
    case SevereDisability;
    case InjuredPeacekeeper;
    case HighMountainResident;    // not on budget-org or state clinic salary

    /**
     * The income tax threshold (resources/law/thresholds.php, or the database) holding this relief's
     * annual allowance — 3,000 GEL under 82.2.ა, 6,000 GEL under 82.2.ბ, ბ¹ and გ as consolidated.
     */
    public function thresholdKey(): string
    {
        return match ($this) {
            self::DisabledSinceChildhood, self::SevereDisability => 'allowance_disability',
            self::InjuredPeacekeeper => 'allowance_peacekeeper',
            self::HighMountainResident => 'allowance_high_mountain',
            default => 'allowance',
        };
    }

    public function article(): string
    {
        return match ($this) {
            self::DisabledSinceChildhood, self::SevereDisability => '82.2.ბ',
            self::InjuredPeacekeeper => '82.2.ბ¹',
            self::HighMountainResident => '82.2.გ',
            default => '82.2.ა',
        };
    }
}
