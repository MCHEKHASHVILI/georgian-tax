<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax;

/** Art. 97.1 — the four things a resident company pays profit tax on, plus deemed distributions. */
enum OutflowKind: string
{
    case Distribution = '98¹.1';
    case DeemedDistribution = '98¹.4';
    case NonBusinessExpense = '98²';
    case FreeSupply = '98³';
    case Representation = '98⁴';
}
