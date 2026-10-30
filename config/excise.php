<?php

/*
 * Excise rates per unit — Tax Code of Georgia, Art. 188.1 (consolidated 30/09/2026).
 * Each row: HS prefix => [unit, GEL per unit, description]. The longest matching prefix wins.
 * Cars, motorcycles, cigarettes and heated tobacco, alcohol and international call termination
 * have their own rules in src/Excise/Rate — they are not in this table.
 *
 * Quantities are given in the row's unit: t = tonne, kg, ml, 1000 m³, stick, 20 pieces.
 */

return [
    // Tobacco
    '2401'           => ['kg', 30, 'Raw tobacco and tobacco waste'],
    '2402 10 000 01' => ['stick', 2.1, 'Cigars'],
    '2403'           => ['kg', 30, 'Smoking, homogenized, chewing and snuff tobacco'],
    '2404 12'        => ['ml', 1.2, 'Nicotine liquids for e-cigarettes'],
    '2404 19 900'    => ['ml', 1.2, 'Other non-combustion nicotine liquids'],

    // Gases
    '2709 00 100'    => ['t', 300, 'Natural gas condensate'],
    '2711'           => ['t', 300, 'Liquefied gases — LNG, propane, butanes, ethylene and others'],
    '2711 21'        => ['1000 m³', 200, 'Natural gas in gaseous state (for vehicles — 182.1.დ)'],
    '2901'           => ['t', 300, 'Saturated and unsaturated acyclic hydrocarbons'],

    // Petroleum products
    '2710 12'        => ['t', 500, 'Light distillates — petrol'],
    '2710 19 1'      => ['t', 440, 'Medium distillates — kerosene, jet fuel'],
    '2710 19 2'      => ['t', 440, 'Medium distillates — kerosene, jet fuel'],
    '2710 19 3'      => ['t', 400, 'Gas oils — diesel'],
    '2710 19 4'      => ['t', 400, 'Gas oils — diesel'],
    '2710 19 7'      => ['t', 800, 'Heavy oils and lubricating oils'],
    '2710 19 8'      => ['t', 800, 'Lubricating oils'],
    '2710 19 9'      => ['t', 800, 'Other lubricating and heavy oils'],
    '2710 20 1'      => ['t', 400, 'Gas oils with biodiesel'],
    '2710 20 9'      => ['t', 800, 'Other oils with biodiesel'],
    '2710 91'        => ['t', 800, 'Waste oils containing PCBs, PCTs or PBBs'],
    '2710 99'        => ['t', 100, 'Waste oils — bilge water and tank washings'],
    '3826'           => ['t', 150, 'Biodiesel and its blends'],
    '3911 90'        => ['t', 400, 'Liquid pyrolysis product'],

    // Coal-tar oils, additives, solvents, lubricants
    '2707 10'        => ['t', 400, 'Benzol'],
    '2707 20'        => ['t', 400, 'Toluol'],
    '2707 30'        => ['t', 400, 'Xylol'],
    '2707 50'        => ['t', 400, 'Aromatic hydrocarbon mixtures'],
    '2707 99'        => ['t', 350, 'Other high-temperature coal-tar oils'],
    '2707 99 800'    => ['t', 400, 'Phenols'],
    '2712 20'        => ['t', 400, 'Paraffin wax'],
    '2902'           => ['t', 400, 'Cyclic hydrocarbons'],
    '3811'           => ['t', 400, 'Anti-knock and other fuel additives'],
    '3814'           => ['t', 400, 'Organic solvents and thinners'],
    '2905'           => ['t', 400, 'Acyclic alcohols — methanol, propanol, butanol, octanol'],
    '2909 19'        => ['t', 400, 'Acyclic ethers, including ETBE'],
    '2912 11'        => ['t', 400, 'Methanal (formaldehyde)'],
    '3403'           => ['t', 800, 'Lubricating preparations'],
];
