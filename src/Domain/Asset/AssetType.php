<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Asset;

enum AssetType
{
    case Dwelling;               // flat or house with its land — Art. 81.3, 82.1.ვ.ა
    case Car;                    // Art. 81.3, 82.1.ვ.ბ
    case Shares;                 // share or stake in a company
    case ListedGeorgianShares;   // Art. 82.1.უ³
    case ListedGeorgianBonds;    // Art. 82.1.უ¹
    case StateOrIfiBonds;        // Art. 82.1.უ
    case RealEstate;             // non-dwelling
    case IntellectualProperty;   // Art. 8.21
    case Other;
}
