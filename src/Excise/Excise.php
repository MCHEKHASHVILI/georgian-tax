<?php

declare(strict_types=1);

namespace GeorgianTax\Excise;

use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Pipeline\Pipeline;
use GeorgianTax\Engine\Tax;
use GeorgianTax\Excise\Rate\RateRule;
use GeorgianTax\Excise\Rate as R;
use GeorgianTax\Excise\Stage;
use LogicException;

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
    public function __construct(private readonly Pipeline $pipeline, private readonly ExciseTable $table) {}

    public static function standard(?ExciseTable $table = null): self
    {
        /** @var list<RateRule> $rates */
        $rates = [new R\Cars(), new R\Motorcycles(), new R\Tobacco(), new R\Alcohol(), new R\Calls(), new R\TableRate()];

        $levy = static function (Duty $d) use ($rates): Outcome {
            foreach ($rates as $rate) {
                if (($outcome = $rate->levy($d)) !== null) {
                    return $outcome;
                }
            }

            throw new LogicException("No excise rate for HS {$d->commodity->hsCode}.");
        };

        return new self(
            new Pipeline([new Stage\IdentifyOperation(), new Stage\ApplyExemptions()], $levy),
            $table ?? ExciseTable::standard(),
        );
    }

    public function name(): string
    {
        return Duty::TAX;
    }

    public function assess(TaxableEvent $event): Outcome
    {
        if ($event instanceof Refund) {
            return $this->assess($event->original)->reversed($event->share());
        }

        return $this->pipeline->process(new Duty($event, $this->table));
    }
}
