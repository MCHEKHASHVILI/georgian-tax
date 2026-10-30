<?php

declare(strict_types=1);

namespace GeorgianTax\ProfitTax;

use Closure;
use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Pipeline\Pipeline;
use GeorgianTax\Engine\Tax;
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
        private readonly Pipeline $pipeline,
        private readonly YearToDate $ledger,
        private readonly RateTable $rates,
        private readonly CompanyBooks $books,
    ) {}

    public static function standard(YearToDate $ledger, RateTable $rates, CompanyBooks $books): self
    {
        $exemptions = [new E\NotADistribution(), new E\ExemptCompanies(), new E\FreeSupplyExceptions()];
        $rules = [new R\RepresentationOverLimit(), new R\InternationalCompany(), new R\StandardRate()];

        $stages = [
            new Stage\IdentifyOutflow(),
            self::firstOf($exemptions, fn (E\Exemption $e, Outflow $o) => $e->exempts($o)),
        ];

        return new self(
            new Pipeline($stages, self::firstOf($rules, fn (R\Rule $r, Outflow $o) => $r->assess($o), terminal: true)),
            $ledger, $rates, $books,
        );
    }

    public function name(): string
    {
        return Outflow::TAX;
    }

    public function assess(TaxableEvent $event): Outcome
    {
        if ($event instanceof Refund) {
            return $this->assess($event->original)->reversed($event->share());
        }

        return $this->pipeline->process(new Outflow($event, $this->ledger, $this->rates, $this->books));
    }

    /**
     * A stage that asks each handler in turn and returns the first answer, or passes on —
     * the same first-match shape as the other taxes' stages.
     *
     * @template T of object
     * @param list<T> $handlers
     * @param Closure(T, Outflow): ?Outcome $ask
     */
    private static function firstOf(array $handlers, Closure $ask, bool $terminal = false): Closure
    {
        return static function (Outflow $o, ?Closure $next = null) use ($handlers, $ask, $terminal): Outcome {
            foreach ($handlers as $handler) {
                if (($outcome = $ask($handler, $o)) !== null) {
                    return $outcome;
                }
            }

            return $terminal ? throw new \LogicException('StandardRate always answers.') : $next($o);
        };
    }
}
