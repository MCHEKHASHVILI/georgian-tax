<?php

declare(strict_types=1);

namespace GeorgianTax\Excise;

use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use GeorgianTax\Engine\Tax;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use GeorgianTax\Excise\Rate as R;

/**
 * Excise as one stage of the engine, and a pipeline of its own:
 *
 *   IdentifyOperation  Art. 182–187  production, import, export, gas for vehicles, call termination
 *   ApplyExemptions    Art. 194      with credit (export, duty-free) and without (import reliefs)
 *   rate rules         Art. 188, 188¹  cars, motorcycles, tobacco, alcohol, calls, the rate table
 *
 * Run it before VAT: on import, excise joins the VAT base (164¹.2.ა).
 */
final class Excise extends Tax
{
    public function __construct(
        Container $container,
        Config $config,
        private readonly ExciseTable $table,
    ) {
        parent::__construct($container, $config);
    }

    public function name(): string
    {
        return Duty::TAX;
    }

    protected function stages(): array
    {
        return [
            Stage\IdentifyOperation::class,
            Stage\ApplyExemptions::class,
            Stage\ApplyRate::class => [R\Cars::class, R\Motorcycles::class, R\Tobacco::class, R\Alcohol::class, R\Calls::class, R\TableRate::class],
        ];
    }

    protected function subject(TaxableEvent $event, ?TaxReport $report): object
    {
        return new Duty($event, $this->table);
    }
}
