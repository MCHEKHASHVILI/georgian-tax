<?php

use GeorgianTax\Excise\Excise;
use GeorgianTax\ImportTax\ImportTax;
use GeorgianTax\IncomeTax\IncomeTax;
use GeorgianTax\Ledger\Eloquent\EloquentBooks;
use GeorgianTax\ProfitTax\ProfitTax;
use GeorgianTax\PropertyTax\PropertyTax;
use GeorgianTax\Vat\Vat;

return [

    /*
    | Georgia's taxes, in the order the engine runs them. Each sees what the earlier ones decided:
    | import tax and excise must come before VAT, which adds them to its import base (Art. 164¹.2.ა).
    | Add your own by extending GeorgianTax\Engine\Tax and listing it here.
    */
    'taxes' => [
        ImportTax::class,
        Excise::class,
        Vat::class,
        IncomeTax::class,
        ProfitTax::class,
        PropertyTax::class,
    ],

    /*
    | Replace one tax's stages without forking the package. Same shape as Tax::stages():
    |     'vat' => [Stage\IdentifyOperation::class, Stage\ApplyExemptions::class => [...], ...],
    | Leave a tax out to keep its built-in stages.
    */
    'pipelines' => [],

    /*
    | Where year-to-date totals live: 'eloquent' (your database) or 'memory' (per request — tests,
    | one-off quotes).
    */
    'ledger' => env('GEORGIAN_TAX_LEDGER', 'eloquent'),

    /* Company revenue, net profit and founding year for the profit tax limits. Bind your own class. */
    'books' => EloquentBooks::class,

    'database' => [
        'connection' => env('GEORGIAN_TAX_DB_CONNECTION'),
        'table_prefix' => 'georgian_tax_',
        // Run the package migrations with yours. Set false and publish them to edit them first.
        'run_migrations' => true,
    ],


    /*
    | Property tax is local (Art. 202, 204). Each municipal council sets its rate within the Code's
    | limits; these are your councils' decisions. Defaults are the caps and the base land rates.
    */
    'property' => [
        'company_percent' => 1.0,            // up to 1%
        'leasing_company_percent' => 0.6,    // up to 0.6%
        'individual_percent' => 1.0,         // 0.8% – 1%
        'natural_resource_per_ha' => 3.0,    // up to 3 GEL
        'land_multipliers' => [               // municipality => up to 1.5, e.g. 'marneuli' => 1.5
        ],
        'territorial_coefficients' => [       // municipality => up to 1.5, e.g. 'tbilisi' => 1.5
        ],
    ],

    /*
    | The tables of the Code itself — rates by article and date, the excise and import tax lists, the
    | land rates. Null uses the copies in the package (consolidated 30/09/2026). Point a key at your
    | own file to follow an amendment before the package catches up.
    */
    'law' => [
        // 'file': rates from resources/law/rates.php. 'database': from the tax_rates table of
        // mchekhashvili/tax-legislation, with their history — seed it with GeorgiaLegislationSeeder.
        'source' => env('GEORGIAN_TAX_LAW_SOURCE', 'file'),
        'rates' => null,
        'excise' => null,
        'import' => null,
        'land' => null,
    ],

];
