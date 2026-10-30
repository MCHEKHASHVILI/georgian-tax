<?php

/*
 * Tax rates, keyed by the article that sets them.
 * Each entry maps an effective-from date to a percent; add a row when the law changes.
 * Source: Tax Code of Georgia, consolidated publication of 30/09/2026.
 *
 * The percents are verified against that publication. The effective-from dates are not historic —
 * they only mark these rates as valid from the Code's start. Don't assess pre-2026 events
 * without checking the rate history for the year in question.
 */

return [
    // General — Art. 81
    '81.1'      => ['2011-01-01' => 20],   // general rate
    '81.2'      => ['2011-01-01' => 5],    // residential rent, no deductions
    '81.3'      => ['2011-01-01' => 5],    // gain on a dwelling with land, or a car
    '81.3¹'     => ['2011-01-01' => 20],   // gaming-hall / online gambling organizer
    '81.3²'     => ['2011-01-01' => 5],    // withdrawals from gaming machines or accounts (Art. 80.8)
    '81.3³'     => ['2011-01-01' => 5],    // organizer's income from foreign players

    // Status regimes
    '23.7'      => ['2017-01-01' => 5],    // international company employees
    '90.1'      => ['2011-01-01' => 1],    // small business
    '90.2'      => ['2011-01-01' => 3],    // small business above the turnover threshold
    '95³.1.ბ'   => ['2011-01-01' => 3],    // fixed tax as a share of income

    // Withholding by income type — Art. 130–133³
    '130.1'     => ['2011-01-01' => 5],    // dividends
    '131.1'     => ['2011-01-01' => 5],    // interest
    '132.1'     => ['2011-01-01' => 20],   // royalty to resident individuals
    '133².1'    => ['2012-01-01' => 5],    // tourist enterprise payouts
    '133³.1'    => ['2011-01-01' => 3],    // listed goods from individuals, no waybill

    // Non-residents without a Georgian PE — Art. 134
    '134.1.ბ¹'  => ['2011-01-01' => 5],    // royalty
    '134.1.გ'   => ['2011-01-01' => 10],   // international transport and telecom
    '134.1.დ'   => ['2011-01-01' => 4],    // oil and gas subcontractors
    '134.1.ე'   => ['2011-01-01' => 10],   // other Georgian-source income
    '134.1¹'    => ['2011-01-01' => 15],   // payee in a preferential-tax country

    // VAT — Art. 166
    '166'       => ['2011-01-01' => 18],
];
