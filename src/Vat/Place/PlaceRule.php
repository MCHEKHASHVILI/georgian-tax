<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Place;

use GeorgianTax\Vat\Supply;

/** One paragraph of Art. 162 or 162¹. True: supplied in Georgia; false: abroad; null: doesn't apply. */
interface PlaceRule
{
    public function inGeorgia(Supply $supply): ?bool;
}
