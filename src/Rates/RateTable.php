<?php

declare(strict_types=1);

namespace GeorgianTax\Rates;

use DateTimeImmutable;
use GeorgianTax\Domain\Rate;

/** Rates keyed by the article that sets them, valid on a date — an amendment is a new row, not a code change. */
interface RateTable
{
    public function rate(string $article, DateTimeImmutable $on): Rate;
}
