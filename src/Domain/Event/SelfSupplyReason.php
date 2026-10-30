<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

/** Why a taxable person is treated as supplying to itself (VAT Art. 160.3, 160¹.2). */
enum SelfSupplyReason: string
{
    case OwnBuildingPutIntoUse = '160.3.ბ';
    case EmployeePersonalUse = '160.3.გ';
    case ActivityCeased = '160.3.დ';
    case Deregistration = '160.3.ე';
    case TouristStatusEnded = '160.3.ზ';
    case Shortage = '160.3.თ';
    case EmployeeServices = '160¹.2.ა';
    case OwnBuildingRepair = '160¹.2.ბ';

    /** These need the person to have credited input VAT on the goods (160.3.გ–ე, 160¹.2.ა). */
    public function needsCreditedInput(): bool
    {
        return in_array($this, [self::EmployeePersonalUse, self::ActivityCeased, self::Deregistration, self::EmployeeServices], true);
    }
}
