<?php

/*
 * Amounts the Tax Code draws lines at — consolidated publication of 30/09/2026.
 * tax => key => [article, [effective-from => GEL]]. '' means "since before the recorded history".
 * Add a dated entry when the law changes, or move to the database (GEORGIAN_TAX_LAW_SOURCE=database).
 */

return [
    'income' => [
        'gifts'                    => ['82.1.თ', ['' => 1_000]],      // gifts received in a year
        'distant_heirs'            => ['82.1.ი', ['' => 150_000]],    // from III- and IV-order heirs in a year
        'agricultural_produce'     => ['82.1.ლ', ['' => 200_000]],    // own produce supplied in a year
        'agricultural_salary'      => ['82.1.ც', ['' => 200_000]],    // employer's produce supplied in a year
        'lottery_win'              => ['82.1.ღ', ['' => 1_000]],      // one win
        'private_pension'          => ['82.1.ბ⁴.ა', ['' => 6_000]],   // contributions in a year
        'small_business'           => ['90.2', ['' => 500_000]],      // turnover in a year
        'small_business_wine'      => ['88.3', ['' => 700_000]],      // wine-making and agro-tourism
        'small_business_salaries'  => ['94.4', ['' => 6_000]],        // salaries untaxed at source in a year
        'small_employer_last_year' => ['94.4', ['' => 50_000]],       // the employer's income the year before
    ],
    'vat' => [
        'registration'             => ['165.1', ['' => 100_000]],     // taxable turnover over 12 months
        'gift_to_person'           => ['157.ი', ['' => 50]],          // gifts to one person in a year
    ],
    'property' => [
        'family_income'            => ['206.1.ა', ['' => 100_000]],   // last year's family income
    ],
];
