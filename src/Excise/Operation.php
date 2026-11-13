<?php

declare(strict_types=1);

namespace GeorgianTax\Excise;

/** What makes an excise event taxable (Art. 183–187), or creditable (189.5), with the article that defines it. */
enum Operation: string
{
    case ProducerSupply = '184.1.ა';
    case GasForVehicles = '184.1.დ';
    case CallTermination = '184.1.ე';
    case RawTobaccoSupply = '184.1.ვ';
    case Import = '186';
    case Export = '187';
    /** Not a taxable operation: excise goods imported or bought for maturing, whose excise is credited (189.5). */
    case Maturing = '189.5';
}
