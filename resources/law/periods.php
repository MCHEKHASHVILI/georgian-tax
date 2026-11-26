<?php

/*
 * Lengths of time the Tax Code draws lines at, which the engine applies rather than the caller.
 * Tax Code of Georgia, consolidated publication of 30/09/2026.
 *
 * table => key => ['article', 'length', 'unit', 'description'] — 'unit' is days, months or years.
 * The key is the article (or part) that sets the period. The rules decide which period applies;
 * the lengths live here (and in the database, GEORGIAN_TAX_LAW_SOURCE=database) so an amendment
 * changes no code.
 */

return [
    'income.periods' => [
        '82.1.ვ.ა'       => ['article' => '82.1.ვ.ა', 'length' => 2, 'unit' => 'years', 'description' => 'A dwelling with its land, held more than'],
        '82.1.ვ.ბ'       => ['article' => '82.1.ვ.ბ', 'length' => 6, 'unit' => 'months', 'description' => 'A car, held from registration more than'],
        '82.1.ვ.გ'       => ['article' => '82.1.ვ.გ', 'length' => 2, 'unit' => 'years', 'description' => 'Any other asset, held more than'],
        '82.1.ვ.გ.note'  => ['article' => '82.1.ვ.გ', 'length' => 2, 'unit' => 'years', 'description' => 'Business use ignored once it ended at least'],
    ],
    'excise.periods' => [
        '189.8'          => ['article' => '189.8', 'length' => 180, 'unit' => 'days', 'description' => 'A car or motorcycle exported within, of its release'],
    ],
    'import.periods' => [
        '198.3'          => ['article' => '198.3', 'length' => 180, 'unit' => 'days', 'description' => 'A car exported within, of its release'],
    ],
];
