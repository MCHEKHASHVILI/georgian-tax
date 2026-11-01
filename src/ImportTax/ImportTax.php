<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax;

use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use GeorgianTax\Engine\Tax;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use GeorgianTax\ImportTax\Rule as R;

/**
 * Import tax as one stage of the engine, and a pipeline of its own:
 *
 *   IdentifyImport   Art. 195–196  goods crossing the customs border, with an HS code
 *   ApplyExemptions  Art. 199      by goods and by relief
 *   rate rules       Art. 197      cars per cm³ and age, alcohol in euro, 12% and 5% lists, else 0%
 *
 * Run it first: VAT adds import tax to its import base (164¹.2.ა).
 * Not modelled: temporary admission at 3% a month (197.4–5).
 */
final class ImportTax extends Tax
{
    public function __construct(
        Container $container,
        Config $config,
        private readonly ImportTable $table,
    ) {
        parent::__construct($container, $config);
    }

    public function name(): string
    {
        return Clearance::TAX;
    }

    protected function stages(): array
    {
        return [
            Stage\IdentifyImport::class,
            Stage\ApplyExemptions::class,
            Stage\ApplyRules::class => [R\PassengerCar::class, R\Alcohol::class, R\ListedRate::class],
        ];
    }

    protected function subject(TaxableEvent $event, ?TaxReport $report): object
    {
        return new Clearance($event, $this->table);
    }
}
