<?php

declare(strict_types=1);

namespace GeorgianTax\Vat;

use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Pipeline\Pipeline;
use GeorgianTax\Engine\Tax;
use GeorgianTax\Ledger\YearToDate;
use GeorgianTax\Rates\RateTable;
use GeorgianTax\Vat\Exemption as E;
use GeorgianTax\Vat\Place as P;
use GeorgianTax\Vat\Rule as R;
use GeorgianTax\Vat\Stage;
use LogicException;

/**
 * VAT as one stage of the engine, and a pipeline of its own:
 *
 *   IdentifyOperation     Art. 158–160³  a supply, an import, or a deemed supply?
 *   ResolvePlaceOfSupply  Art. 162–162¹  supplied in Georgia?
 *   ApplyExemptions       Art. 170–173   exempt with credit, without credit, or exempt import
 *   ApplyLiability        Art. 161, 165, 168   who pays, and when
 *
 * A refund is the original assessment reversed in proportion (Art. 179 adjustment).
 */
final class Vat extends Tax
{
    public function __construct(
        private readonly Pipeline $pipeline,
        private readonly YearToDate $ledger,
        private readonly RateTable $rates,
    ) {}

    public static function standard(YearToDate $ledger, RateTable $rates): self
    {
        $stages = [
            new Stage\IdentifyOperation(),
            new Stage\ResolvePlaceOfSupply([
                new P\GoodsLocation(),
                new P\ImmovableProperty(),
                new P\WherePerformed(),
                new P\ConsumerLocation(),
                new P\BusinessCustomer(),
                new P\SupplierLocation(),
            ]),
            new Stage\ApplyExemptions([
                new E\ExemptImports(),
                new E\Export(),
                new E\PartyExemptions(),
                new E\ExemptCategories(),
            ]),
            new Stage\ApplyLiability([
                new R\ImportVat(),
                new R\ReverseCharge(),
                new R\ForeignSupplierToConsumer(),
                new R\RegisteredSupplier(),
                new R\ObligedFromFirstSupply(),
                new R\RegistrationThreshold(),
            ]),
        ];

        $unreachable = static fn () => throw new LogicException('ApplyLiability always answers.');

        return new self(new Pipeline($stages, $unreachable), $ledger, $rates);
    }

    public function name(): string
    {
        return Supply::TAX;
    }

    public function assess(TaxableEvent $event): Outcome
    {
        if ($event instanceof Refund) {
            return $this->assess($event->original)->reversed($event->share());
        }

        return $this->pipeline->process(new Supply($event, $this->ledger, $this->rates));
    }
}
