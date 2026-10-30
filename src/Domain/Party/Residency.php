<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Party;

enum Residency
{
    case Resident;
    case NonResident;
}
