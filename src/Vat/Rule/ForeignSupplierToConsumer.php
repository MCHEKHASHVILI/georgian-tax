<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Rule;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\Vat\Supply;

/**
 * A foreign supplier with no Georgian establishment serves a consumer in Georgia — typically
 * electronic services. Nobody can reverse-charge, so the supplier itself must account for VAT,
 * on Minister of Finance terms.
 */
#[Article('165².2.ე', 'Foreign supplier to a consumer')]
final class ForeignSupplierToConsumer implements Rule
{
    public function assess(Supply $s): ?Outcome
    {
        $supplier = $s->supplier();

        if (!$s->isService() || $supplier->isResident() || $supplier->hasGeorgianPE) {
            return null;
        }

        return $s->charged($this, Collection::MonthlyDeclaration, [
            'Registration and reporting follow the Minister of Finance order for foreign suppliers.',
        ]);
    }
}
