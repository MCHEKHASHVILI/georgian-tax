<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax;

use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Tax;
use GeorgianTax\Engine\TaxReport;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use GeorgianTax\Ledger\CompanyBooks;
use GeorgianTax\Ledger\YearToDate;
use GeorgianTax\ProfitTax\Exemption as E;
use GeorgianTax\ProfitTax\Rule as R;
use GeorgianTax\Rates\RateTable;

/**
 * Profit tax as one stage of the engine, and a pipeline of its own:
 *
 *   IdentifyOutflow   Art. 96–97   a resident company paying something out?
 *   exemptions        Art. 98¹.2, 98³.3, 99, 23¹   not a distribution, an exempt company, an untaxed gift
 *   rules             Art. 98, 98⁴, 23   representation over the limit, international company, 15%
 */
final class ProfitTax extends Tax
{
    public function __construct(
        Container $container,
        Config $config,
        private readonly YearToDate $ledger,
        private readonly RateTable $rates,
        private readonly CompanyBooks $books,
    ) {
        parent::__construct($container, $config);
    }

    public function name(): string
    {
        return Outflow::TAX;
    }

    protected function stages(): array
    {
        return [
            Stage\IdentifyOutflow::class,
            Stage\ApplyExemptions::class => [E\NotADistribution::class, E\ExemptCompanies::class, E\FreeSupplyExceptions::class],
            Stage\ApplyRules::class => [R\RepresentationOverLimit::class, R\InternationalCompany::class, R\StandardRate::class],
        ];
    }

    protected function subject(TaxableEvent $event, ?TaxReport $report): object
    {
        return new Outflow($event, $this->ledger, $this->rates, $this->books);
    }
}
