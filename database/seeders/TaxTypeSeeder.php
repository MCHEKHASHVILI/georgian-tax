<?php

declare(strict_types=1);

namespace GeorgianTax\Database\Seeders;

use GeorgianTax\Excise\Excise;
use GeorgianTax\ImportTax\ImportTax;
use GeorgianTax\IncomeTax\IncomeTax;
use GeorgianTax\Legislation\Models\TaxType;
use GeorgianTax\Legislation\TaxLevel;
use GeorgianTax\ProfitTax\ProfitTax;
use GeorgianTax\PropertyTax\PropertyTax;
use GeorgianTax\Vat\Vat;
use Illuminate\Database\Seeder;

/**
 * The taxes Art. 6.5–6.6 of the Tax Code of Georgia lists today (Code №3591-IIს of 17 September
 * 2010, Art. 6 as amended by Law №6942 of 15 July 2020).
 *
 * `in_force_from` is left empty: each of these existed before the current Code, and their earlier
 * history — and the taxes since abolished — is to be recorded on top of this.
 *
 * Run: php artisan db:seed --class="GeorgianTax\Database\Seeders\TaxTypeSeeder"
 */
final class TaxTypeSeeder extends Seeder
{
    private const CODE = 'Tax Code of Georgia (№3591-IIს), Art. ';

    public function run(): void
    {
        $taxes = [
            ['income', 'საშემოსავლო გადასახადი', 'Income tax', TaxLevel::National, '6.5.ა', IncomeTax::class],
            ['profit', 'მოგების გადასახადი', 'Profit tax', TaxLevel::National, '6.5.ბ', ProfitTax::class],
            ['vat', 'დამატებული ღირებულების გადასახადი (დღგ)', 'Value added tax (VAT)', TaxLevel::National, '6.5.გ', Vat::class],
            ['excise', 'აქციზი', 'Excise', TaxLevel::National, '6.5.დ', Excise::class],
            ['import', 'იმპორტის გადასახადი', 'Import tax', TaxLevel::National, '6.5.ე', ImportTax::class],
            ['property', 'ქონების გადასახადი', 'Property tax', TaxLevel::Local, '6.6', PropertyTax::class],
        ];

        foreach ($taxes as [$code, $ka, $en, $level, $article, $engine]) {
            TaxType::query()->updateOrCreate(
                ['code' => $code, 'in_force_from' => null],
                ['name_ka' => $ka, 'name_en' => $en, 'level' => $level, 'engine' => $engine, 'legal_basis' => self::CODE . $article],
            );
        }
    }
}
