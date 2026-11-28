<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax;

use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use GeorgianTax\Engine\Tax;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use GeorgianTax\Ledger\CompanyBooks;
use GeorgianTax\Ledger\YearToDate;
use GeorgianTax\ProfitTax\Exemption as E;
use GeorgianTax\ProfitTax\Rule as R;

/**
 * Profit tax as one stage of the engine, and a pipeline of its own:
 *
 *   AssessYear        Art. 97.12, 98.4   a bank or lender closing its year: 20% of the year's profit
 *   AssessGamblingMonth  Art. 97.9–9³, 98.5–6, 309.16   a gambling organizer closing its month
 *   AssessRentalYear  Art. 97.11, 98.1   a non-resident company paid rent by non-agents, closing its year
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
            Stage\AssessYear::class,
            Stage\AssessGamblingMonth::class,
            Stage\AssessRentalYear::class,
            Stage\IdentifyOutflow::class,
            Stage\ApplyExemptions::class => [E\NotADistribution::class, E\ExemptCompanies::class, E\FreeSupplyExceptions::class],
            Stage\ApplyRules::class => [R\RepresentationOverLimit::class, R\InternationalCompany::class, R\StandardRate::class],
        ];
    }

    protected function subject(TaxableEvent $event, ?TaxReport $report): object
    {
        return new Outflow($event, $this->ledger, $this->rateTable(), $this->books);
    }
}
