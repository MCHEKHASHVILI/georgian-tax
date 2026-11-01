<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax\Rule;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use GeorgianTax\ImportTax\Clearance;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Wine, vermouth, fermented drinks, ethyl alcohol, spirits and vinegar (197.3), in euro:
 * per litre, or for spirits per % vol per hectolitre. The rate is converted at the import's
 * euro rate; without one the amount cannot be computed.
 */
#[Article('197.3', 'Alcohol import tax in euro')]
final class Alcohol implements Rule
{
    public function assess(Clearance $c): ?Outcome
    {
        $row = $c->table->alcoholFor($c->commodity);

        if ($row === null) {
            return null;
        }

        $eur = $c->import->eurToGel;

        if ($eur === null) {
            return $c->unresolved($this, 'Alcohol import tax is set in euro — give the import its GEL per euro rate.');
        }

        $goods = $c->commodity;
        $basis = new Citation('197.3', $row['description']);
        $note = "€{$row['euro']} at $eur GEL per euro.";

        if ($row['measure'] === 'per_vol_hl') {
            $volHl = $goods->quantity / 100 * ($goods->alcoholPercent ?? 0);

            return $c->levied($basis, $volHl, '% vol × hl', $row['euro'] * $eur, notes: [$note]);
        }

        return $c->levied($basis, $goods->quantity, 'litre', $row['euro'] * $eur, notes: [$note]);
    }
}
