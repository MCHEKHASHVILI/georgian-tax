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
    /** 82.2.ა.ვ — lives permanently in a high-mountain settlement, three or more children under 18 in their keep. */
    case HighMountainParentOfThree;
    /** 82.2.ა.ვ — lives permanently in a high-mountain settlement, one or two children under 18 in their keep. */
    case HighMountainParentOfOneOrTwo;

    /**
     * The income tax threshold (resources/law/thresholds.php, or the database) holding this relief's
     * annual allowance — 3,000 GEL under 82.2.ა, 6,000 GEL under 82.2.ბ, ბ¹ and გ as consolidated.
     * 82.2.ა.ვ's three-or-more-children case reads 82.2.ა's own 3,000 (the opening words' "up to 3,000 GEL";
     * provisional, asked in docs/ROADMAP.md); its one-or-two case has its own 3,000, on which the tax is halved.
     */
    public function thresholdKey(): string
    {
        return match ($this) {
            self::DisabledSinceChildhood, self::SevereDisability => 'allowance_disability',
            self::InjuredPeacekeeper => 'allowance_peacekeeper',
            self::HighMountainResident => 'allowance_high_mountain',
            self::HighMountainParentOfOneOrTwo => 'allowance_high_mountain_parent',
            default => 'allowance',
        };
    }

    /** 82.2.ა.ვ — the relief reaches only salary from a budget organization in a high-mountain settlement. */
    public function onlyBudgetSalaryInHighMountains(): bool
    {
        return $this === self::HighMountainParentOfThree || $this === self::HighMountainParentOfOneOrTwo;
    }

    /** The income tax rate (resources/law/rates.php) by which this relief cuts the tax on what it shelters, if not all of it. */
    public function reductionRate(): ?string
    {
        return $this === self::HighMountainParentOfOneOrTwo ? '82.2.ა.ვ' : null;
    }

    public function article(): string
    {
        return match ($this) {
            self::DisabledSinceChildhood, self::SevereDisability => '82.2.ბ',
            self::InjuredPeacekeeper => '82.2.ბ¹',
            self::HighMountainResident => '82.2.გ',
            self::HighMountainParentOfThree, self::HighMountainParentOfOneOrTwo => '82.2.ა.ვ',
            default => '82.2.ა',
        };
    }
}
