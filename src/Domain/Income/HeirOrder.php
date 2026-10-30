<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Income;

/** Art. 82.4.გ — relation of a donor or deceased to the recipient. */
enum HeirOrder
{
    case First;   // spouse, child, adopted child, grandchild and below, parent, adoptive parent
    case Second;  // sibling, nephew, niece and their children
    case Third;   // grandparents and great-grandparents
    case Fourth;  // uncle, aunt
    case None;
}
