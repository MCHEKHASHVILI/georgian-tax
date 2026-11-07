<?php

/*
 * The excise rates that the rules in src/Excise/Rate apply, rather than the HS table in excise.php:
 * cars (188.9–17), motorcycles by age (188.1, row 4¹), cigarettes, cigarillos and heated tobacco
 * (188.1 with 188.1¹), alcoholic drinks (188¹.1) and international call termination (188.3).
 * Tax Code of Georgia, consolidated publication of 30/09/2026.
 *
 * table => key => ['article', 'unit', 'rate', 'description'] — 'rate' in GEL per unit, or for a
 * '×' or '%' unit the multiplier or reduction the article sets. Tobacco rows add 'percent', the
 * share of the retail price (188.1¹). The rules decide which row applies; the figures live here
 * (and in the database, GEORGIAN_TAX_LAW_SOURCE=database) so an amendment changes no code.
 */

return [
    'excise.188.cars' => [
        '188.9'  => ['article' => '188.9', 'unit' => 'car', 'rate' => 100, 'description' => 'Sports car'],
        '188.10' => ['article' => '188.10', 'unit' => 'cm³', 'rate' => 1.0, 'description' => 'Classic car'],
        '188.12' => ['article' => '188.12', 'unit' => 'cm³', 'rate' => 1.5, 'description' => 'Passenger car, 0 to 6 years'],
        '188.13' => ['article' => '188.13', 'unit' => 'cm³', 'rate' => 4.5, 'description' => 'Passenger car, over 6 years'],
        '188.14' => ['article' => '188.14', 'unit' => '%', 'rate' => 60, 'description' => 'Left-hand-drive hybrid, 0 to 6 years: 188.12 rate reduced by'],
        '188.15' => ['article' => '188.15', 'unit' => '×', 'rate' => 3, 'description' => 'Right-hand drive, 0 to 6 years: times the 188.12 rate'],
        '188.16' => ['article' => '188.16', 'unit' => '×', 'rate' => 3, 'description' => 'Right-hand drive, over 6 years: times the 188.13 rate'],
        '188.17' => ['article' => '188.17', 'unit' => 'car', 'rate' => 3000, 'description' => 'Right-hand-drive electric car'],
    ],

    // Age in years (the event year minus the year made); '>14' is row ჟ, "over 14 years".
    'excise.188.1.motorcycles' => [
        '0'   => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 1.5, 'description' => 'Motorcycle, under 1 year (ა)'],
        '1'   => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 1.3, 'description' => 'Motorcycle, 1 year (ბ)'],
        '2'   => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 0.7, 'description' => 'Motorcycle, 2 years (გ)'],
        '3'   => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 0.7, 'description' => 'Motorcycle, 3 years (დ)'],
        '4'   => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 0.7, 'description' => 'Motorcycle, 4 years (ე)'],
        '5'   => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 0.7, 'description' => 'Motorcycle, 5 years (ვ)'],
        '6'   => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 0.8, 'description' => 'Motorcycle, 6 years (ზ)'],
        '7'   => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 0.8, 'description' => 'Motorcycle, 7 years (თ)'],
        '8'   => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 0.8, 'description' => 'Motorcycle, 8 years (ი)'],
        '9'   => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 0.8, 'description' => 'Motorcycle, 9 years (კ)'],
        '10'  => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 1.0, 'description' => 'Motorcycle, 10 years (ლ)'],
        '11'  => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 1.3, 'description' => 'Motorcycle, 11 years (მ)'],
        '12'  => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 1.5, 'description' => 'Motorcycle, 12 years (ნ)'],
        '13'  => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 1.8, 'description' => 'Motorcycle, 13 years (ო)'],
        '14'  => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 2.1, 'description' => 'Motorcycle, 14 years (პ)'],
        '>14' => ['article' => '188.1', 'unit' => 'cm³', 'rate' => 2.4, 'description' => 'Motorcycle, over 14 years (ჟ)'],
    ],

    // 'rate' per 20 from the 188.1 table, plus 'percent' of the retail price per 20 (188.1¹).
    'excise.188.1.tobacco' => [
        'cigarettes'  => ['article' => '188.1¹.ა', 'unit' => '20 sticks', 'rate' => 2.75, 'percent' => 20, 'description' => 'Cigarettes (2402 20)'],
        'local_quota' => ['article' => '188.1¹.ა¹', 'unit' => '20 sticks', 'rate' => 1.3, 'percent' => 15, 'description' => 'Local cigarettes, up to 35 million packs a year (2402 20)'],
        'heated'      => ['article' => '188.1¹.ბ', 'unit' => '20 sticks', 'rate' => 2.70, 'percent' => 20, 'description' => 'Heated tobacco, capsules and the like (2404 11 000 00)'],
        'cigarillos'  => ['article' => '188.1¹.გ', 'unit' => '20 sticks', 'rate' => 2.4, 'percent' => 30, 'description' => 'Cigarillos (2402 10 000 02)'],
    ],

    // By row of the 188¹.1 table.
    'excise.188¹.1' => [
        '1' => ['article' => '188¹.1', 'unit' => 'litre × % vol', 'rate' => 0.12, 'description' => 'Beer'],
        '2' => ['article' => '188¹.1', 'unit' => 'litre', 'rate' => 5, 'description' => 'Other fermented drink over 5%'],
        '3' => ['article' => '188¹.1', 'unit' => 'litre', 'rate' => 0.6, 'description' => 'Other fermented drink 5% or less'],
        '4' => ['article' => '188¹.1', 'unit' => 'litre', 'rate' => 5, 'description' => 'Intermediate drink'],
        '5' => ['article' => '188¹.1', 'unit' => 'litre pure alcohol', 'rate' => 22.5, 'description' => 'High-strength drink'],
        '6' => ['article' => '188¹.1', 'unit' => 'litre pure alcohol', 'rate' => 7.5, 'description' => 'Ethyl alcohol (2207)'],
        '7' => ['article' => '188¹.1', 'unit' => 'litre pure alcohol', 'rate' => 15, 'description' => 'Brandy, rum, liqueur and the like (2208 20, 2208 60, 2208 90 330/380/480/710)'],
    ],

    'excise.188.3' => [
        'mobile' => ['article' => '188.3.ბ', 'unit' => 'minute', 'rate' => 0.15, 'description' => 'International call ending on a mobile network'],
        'fixed'  => ['article' => '188.3.გ', 'unit' => 'minute', 'rate' => 0.08, 'description' => 'International call ending on a fixed network'],
    ],
];
