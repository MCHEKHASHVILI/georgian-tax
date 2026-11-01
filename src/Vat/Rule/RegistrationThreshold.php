<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Rule;

use GeorgianTax\Domain\Event\Pay;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Law\Article;
use Mchekhashvili\TaxLegislation\Law\Citation;
use GeorgianTax\Vat\Supply;

/**
 * Catch-all for unregistered suppliers. Taxable supplies over any rolling 12 months are added up;
 * the supply that takes the total past 100,000 GEL is taxed, and every one after it (165.2). The
 * supplier must ask to register within 2 working days (165.1). Below the line there is no VAT,
 * but the supply still counts. Income from a fixed-tax activity doesn't count (165.7¹).
 */
#[Article('165.1', 'Registration threshold')]
final class RegistrationThreshold implements Rule
{
    public function assess(Supply $s): ?Outcome
    {
        if ($s->event instanceof Pay && $s->event->fromFixedTaxActivity) {
            return $s->notTaxable(new Citation('165.7¹'), 'Fixed-tax activity income does not count toward VAT registration.');
        }

        $since = $s->event->date()->modify('-12 months');
        $turnover = $s->ledger->turnover($s->supplier(), Supply::TAX, $since)->plus($s->base());

        if ($turnover->greaterThan($s->threshold('registration'))) {
            return $s->charged($this, Collection::MonthlyDeclaration, [
                "Taxable turnover over the last 12 months reached $turnover; VAT applies from this supply (165.2). Register within 2 working days.",
            ]);
        }

        return $s->notTaxable($this, "Unregistered supplier below 100,000 GEL over 12 months ($turnover so far); no VAT, but this counts toward the threshold.", countsAsTurnover: true);
    }
}
