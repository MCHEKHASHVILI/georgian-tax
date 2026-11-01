<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax\Rule;

use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;
use GeorgianTax\PropertyTax\Holding;

/**
 * A company's or organization's property, at up to 1% of its average annual net book value (202.1).
 * Real estate on the books at historic cost is revalued (202.1.ა–დ):
 *   acquired before 2000 ×3 · 2000–2003 ×2 · 2004 ×1.5 · unknown ×3
 * unless the company revalues its real estate and has audited statements (202.2.ა).
 * Property leased out is taxed on its book value at leasing (202.3); a leasing company pays up to
 * 0.6% of the initial book value for the whole lease (202.3¹).
 */
#[Article('202.1–3¹', 'Company property')]
final class CompanyProperty implements Rule
{
    public function assess(Holding $h): ?Outcome
    {
        $item = $h->item();

        if ($h->isIndividual()) {
            return null;
        }

        if ($item->value === null) {
            return $h->unresolved($this, 'Company property is taxed on its average annual net book value — give it.');
        }

        if ($item->byLeasingCompany) {
            return $h->taxed(new Citation('202.3¹', 'Leasing company'), $item->value, $h->rates->leasingCompanyPercent);
        }

        if ($item->leasedOut) {
            return $h->taxed(new Citation('202.3', 'Property leased out'), $item->value, $h->rates->companyPercent);
        }

        $factor = $item->kind->isRealEstate() && !$item->revaluedWithAudit ? self::revaluation($item->acquiredYear) : 1.0;
        $notes = $factor === 1.0 ? [] : ["Real estate revalued ×$factor for its acquisition year (202.1)."];

        return $h->taxed(new Citation('202.1', 'Company property'), $item->value->multipliedBy($factor), $h->rates->companyPercent, $notes);
    }

    private static function revaluation(?int $acquired): float
    {
        return match (true) {
            $acquired === null, $acquired < 2000 => 3.0,
            $acquired < 2004 => 2.0,
            $acquired === 2004 => 1.5,
            default => 1.0,
        };
    }
}
