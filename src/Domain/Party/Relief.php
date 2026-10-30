<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Party;

use GeorgianTax\Domain\Money;

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

    public function annualAllowance(): Money
    {
        return match ($this) {
            self::Veteran, self::MotherOfGeorgia, self::SingleParent,
            self::AdoptiveParent, self::FosterParent => Money::gel(3000),
            self::DisabledSinceChildhood, self::SevereDisability,
            self::InjuredPeacekeeper, self::HighMountainResident => Money::gel(6000),
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
