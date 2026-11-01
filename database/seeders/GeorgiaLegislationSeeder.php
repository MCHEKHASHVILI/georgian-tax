<?php

declare(strict_types=1);

namespace GeorgianTax\Database\Seeders;

use GeorgianTax\Excise\Excise;
use GeorgianTax\ImportTax\ImportTax;
use GeorgianTax\IncomeTax\IncomeTax;
use GeorgianTax\ProfitTax\ProfitTax;
use GeorgianTax\PropertyTax\PropertyTax;
use GeorgianTax\Vat\Vat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Mchekhashvili\Countries\Database\Seeders\CountrySeeder;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Legislation\ActKind;
use Mchekhashvili\TaxLegislation\Legislation\Models\Exemption;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TableEntry;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxRate;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\Models\Threshold;
use Mchekhashvili\TaxLegislation\Legislation\TaxLevel;
use Mchekhashvili\TaxLegislation\Revisions\Editor;

/**
 * Georgia's tax law as it stands, into mchekhashvili/tax-legislation's tables:
 *
 *   legal acts   the Tax Code of 17 September 2010 (№3591-IIს, in force 1 January 2011, Art. 310.1),
 *                the 2004 Tax Code it repealed (Art. 310.2.ა), and Law №6942 of 15 July 2020,
 *                the latest amendment of Art. 6
 *   tax types    the six taxes Art. 6.5–6.6 lists
 *   rates        resources/law/rates.php, by article
 *   tables       the excise (188.1), import tax (197, 199.პ) and land (204.1) tables
 *   thresholds   resources/law/thresholds.php — every amount the engine draws a line at
 *   exemptions   resources/law/exemptions.php — that each exemption exists; conditions stay in code
 *
 * Values come from the consolidated publication of 30/09/2026. Their start dates are left open
 * (`null`: in force since before the history recorded here); add the history with amend().
 * Its changes are logged as revisions by "seeder:…", with the consolidation as source.
 *
 * Safe to run again: it replaces what it seeded before for Georgia.
 *
 *     php artisan db:seed --class="GeorgianTax\Database\Seeders\GeorgiaLegislationSeeder"
 */
final class GeorgiaLegislationSeeder extends Seeder
{
    private const string LAW = __DIR__ . '/../../resources/law/';

    public function run(): void
    {
        if (!Country::query()->where('alpha2', 'GE')->exists()) {
            $this->call(CountrySeeder::class);
        }

        $georgia = Country::code('GE');

        app(Editor::class)->as('seeder:' . self::class, 'Georgian tax law as consolidated on 30/09/2026', 'https://matsne.gov.ge/ka/document/view/1043717')->run(fn () => DB::transaction(function () use ($georgia) {
            $types = TaxType::query()->ofCountry($georgia)->pluck('id');
            foreach ([TaxRate::class, Threshold::class, Exemption::class, TableEntry::class] as $model) {
                $model::query()->whereIn('tax_type_id', $types)->delete();
            }
            TaxType::query()->whereIn('id', $types)->update(['replaces_id' => null]);
            TaxType::query()->whereIn('id', $types)->delete();
            LegalAct::query()->ofCountry($georgia)->update(['amends_id' => null]);
            LegalAct::query()->ofCountry($georgia)->delete();

            [$code] = $this->acts($georgia);
            $taxes = $this->taxTypes($georgia);

            $this->rates($taxes, $code);
            $this->tables($taxes, $code);
            $this->thresholds($taxes, $code);
            $this->exemptions($taxes, $code);
        }));
    }

    /** @return array{0: LegalAct, 1: LegalAct, 2: LegalAct} */
    private function acts(Country $georgia): array
    {
        $old = LegalAct::query()->create([
            'country_id' => $georgia->id, 'kind' => ActKind::Code, 'title' => 'Tax Code of Georgia (2004)',
            'issuer' => 'Parliament of Georgia', 'published_on' => '2004-12-30',
            'url' => 'https://matsne.gov.ge/ka/document/view/32842',
            'notes' => 'Legislative Herald of Georgia №41, 30.12.2004, art. 200. Repealed from tax periods starting 1 January 2011 by the 2010 Tax Code, Art. 310.2.ა.',
        ]);

        $code = LegalAct::query()->create([
            'country_id' => $georgia->id, 'kind' => ActKind::Code, 'number' => '3591-IIს', 'title' => 'Tax Code of Georgia',
            'issuer' => 'Parliament of Georgia', 'adopted_on' => '2010-09-17', 'effective_on' => '2011-01-01',
            'url' => 'https://matsne.gov.ge/ka/document/view/1043717',
            'notes' => 'In force from 1 January 2011 (Art. 310.1). Values seeded from the consolidated publication of 30/09/2026.',
        ]);

        $article6 = LegalAct::query()->create([
            'country_id' => $georgia->id, 'kind' => ActKind::Law, 'number' => '6942', 'title' => 'Law of Georgia',
            'issuer' => 'Parliament of Georgia', 'adopted_on' => '2020-07-15', 'published_on' => '2020-07-28',
            'url' => 'https://matsne.gov.ge/ka/document/view/4917364', 'amends_id' => $code->id,
            'notes' => 'The latest amendment of Art. 6, the list of taxes.',
        ]);

        return [$code, $old, $article6];
    }

    /** @return array<string, TaxType> by code */
    private function taxTypes(Country $georgia): array
    {
        $taxes = [
            ['income', 'საშემოსავლო გადასახადი', 'Income tax', TaxLevel::National, '6.5.ა', IncomeTax::class],
            ['profit', 'მოგების გადასახადი', 'Profit tax', TaxLevel::National, '6.5.ბ', ProfitTax::class],
            ['vat', 'დამატებული ღირებულების გადასახადი (დღგ)', 'Value added tax (VAT)', TaxLevel::National, '6.5.გ', Vat::class],
            ['excise', 'აქციზი', 'Excise', TaxLevel::National, '6.5.დ', Excise::class],
            ['import', 'იმპორტის გადასახადი', 'Import tax', TaxLevel::National, '6.5.ე', ImportTax::class],
            ['property', 'ქონების გადასახადი', 'Property tax', TaxLevel::Local, '6.6', PropertyTax::class],
        ];

        $made = [];

        foreach ($taxes as [$code, $ka, $en, $level, $article, $engine]) {
            $made[$code] = TaxType::query()->create([
                'country_id' => $georgia->id, 'code' => $code, 'names' => ['ka' => $ka, 'en' => $en],
                'level' => $level, 'engine' => $engine, 'legal_basis' => "Art. $article",
            ]);
        }

        return $made;
    }

    /** resources/law/rates.php — each article to the tax it belongs to. @param array<string, TaxType> $taxes */
    private function rates(array $taxes, LegalAct $code): void
    {
        foreach (require self::LAW . 'rates.php' as $article => $history) {
            $tax = $taxes[$this->taxOfRate((string) $article)];
            ksort($history);
            $first = true;

            foreach ($history as $from => $percent) {
                $values = ['percent' => $percent];

                $first
                    ? TaxRate::query()->create([...$values, 'tax_type_id' => $tax->id, 'key' => (string) $article, 'article' => (string) $article, 'legal_act_id' => $code->id])
                    : TaxRate::amend($tax, (string) $article, $values, from: $from, by: $code, article: (string) $article);

                $first = false;
            }
        }

        // Land rates that are one figure rather than a table (204.2.ა, 204.3).
        $land = require self::LAW . 'property.php';
        TaxRate::query()->create(['tax_type_id' => $taxes['property']->id, 'key' => '204.2.ა', 'article' => '204.2.ა', 'name' => 'Non-agricultural land, base',
            'amount' => Money::GEL($land['non_agricultural_per_m2']), 'unit' => 'm²', 'legal_act_id' => $code->id]);
        TaxRate::query()->create(['tax_type_id' => $taxes['property']->id, 'key' => '204.3', 'article' => '204.3', 'name' => 'Natural-resource land, cap',
            'amount' => Money::GEL($land['natural_resource_per_ha']), 'unit' => 'ha', 'legal_act_id' => $code->id]);
    }

    /** Which tax an article in rates.php sets. */
    private function taxOfRate(string $article): string
    {
        $chapter = (float) strtok($article, '.¹²³⁴');

        return match (true) {
            $chapter >= 96 && $chapter < 100, $article === '23.10' => 'profit',
            $chapter >= 154 && $chapter < 182 => 'vat',
            default => 'income',
        };
    }

    /** @param array<string, TaxType> $taxes */
    private function tables(array $taxes, LegalAct $code): void
    {
        $rows = [];
        $add = function (TaxType $tax, string $table, string $article, string $key, array $values) use (&$rows, $code) {
            $rows[] = [
                'tax_type_id' => $tax->id, 'table' => $table, 'key' => $key, 'article' => $article,
                'values' => json_encode($values, JSON_UNESCAPED_UNICODE), 'legal_act_id' => $code->id,
                'created_at' => now(), 'updated_at' => now(),
            ];
        };

        foreach (require self::LAW . 'excise.php' as $prefix => [$unit, $rate, $description]) {
            $add($taxes['excise'], 'excise.188.1', '188.1', (string) $prefix, ['unit' => $unit, 'rate' => (float) $rate, 'description' => $description]);
        }

        $import = require self::LAW . 'import.php';
        foreach ($import['12'] as $hs) {
            $add($taxes['import'], 'import.197.1', '197.1', $hs, ['percent' => 12]);
        }
        foreach ($import['5'] as $hs) {
            $add($taxes['import'], 'import.197.2', '197.2', $hs, ['percent' => 5]);
        }
        foreach ($import['alcohol'] as $hs => [$euro, $measure, $description]) {
            $add($taxes['import'], 'import.197.3', '197.3', (string) $hs, ['euro' => (float) $euro, 'measure' => $measure, 'description' => $description]);
        }
        foreach ($import['exempt'] as $hs) {
            $add($taxes['import'], 'import.199.პ', '199.პ', $hs, ['exempt' => true]);
        }
        foreach ($import['exempt_except'] as $hs) {
            $add($taxes['import'], 'import.199.პ', '199.პ', $hs, ['exempt' => false]);
        }

        $land = require self::LAW . 'property.php';
        foreach (['arable' => '204.1.ა', 'hayfield' => '204.1.ბ', 'pasture' => '204.1.ბ'] as $use => $article) {
            foreach ($land[$use] as $municipality => $gel) {
                $add($taxes['property'], "property.204.1.$use", $article, $municipality, ['gel_per_ha' => (float) $gel]);
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            TableEntry::query()->insert($chunk);
        }
    }

    /** resources/law/thresholds.php. @param array<string, TaxType> $taxes */
    private function thresholds(array $taxes, LegalAct $code): void
    {
        foreach (require self::LAW . 'thresholds.php' as $tax => $lines) {
            foreach ($lines as $key => [$article, $history]) {
                ksort($history);
                $first = true;

                foreach ($history as $from => $gel) {
                    $values = ['amount' => Money::GEL($gel), 'name' => self::label($key)];

                    $first
                        ? Threshold::query()->create([...$values, 'tax_type_id' => $taxes[$tax]->id, 'key' => $key, 'article' => $article, 'legal_act_id' => $code->id])
                        : Threshold::amend($taxes[$tax], $key, $values, from: (string) $from, by: $code, article: $article);

                    $first = false;
                }
            }
        }
    }

    /** resources/law/exemptions.php — that each exemption the engine applies exists; its conditions stay in code. @param array<string, TaxType> $taxes */
    private function exemptions(array $taxes, LegalAct $code): void
    {
        $rows = [];

        foreach (require self::LAW . 'exemptions.php' as $tax => $exemptions) {
            foreach ($exemptions as $article => [$name, $withCredit]) {
                $rows[] = [
                    'tax_type_id' => $taxes[$tax]->id, 'key' => (string) $article, 'article' => (string) $article,
                    'name' => mb_substr($name, 0, 255), 'with_credit' => $withCredit, 'legal_act_id' => $code->id,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }

        Exemption::query()->insert($rows);
    }

    private static function label(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
