<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Party;

/** Art. 83 — special taxation regimes for individuals. */
enum Regime
{
    case General;
    case MicroBusiness;  // Art. 84–87
    case SmallBusiness;  // Art. 88–95
    case FixedTax;       // Art. 95¹–95⁶
}
