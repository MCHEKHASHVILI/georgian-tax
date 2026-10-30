<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax;

use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Pipeline\Pipeline;
use GeorgianTax\Engine\Tax;
use GeorgianTax\ImportTax\Rule as R;
use GeorgianTax\ImportTax\Stage;
use LogicException;

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
    public function __construct(private readonly Pipeline $pipeline, private readonly ImportTable $table) {}

    public static function standard(?ImportTable $table = null): self
    {
        /** @var list<R\Rule> $rules */
        $rules = [new R\PassengerCar(), new R\Alcohol(), new R\ListedRate()];

        $assess = static function (Clearance $c) use ($rules): Outcome {
            foreach ($rules as $rule) {
                if (($outcome = $rule->assess($c)) !== null) {
                    return $outcome;
                }
            }

            throw new LogicException('ListedRate answers every import.');
        };

        return new self(
            new Pipeline([new Stage\IdentifyImport(), new Stage\ApplyExemptions()], $assess),
            $table ?? ImportTable::standard(),
        );
    }

    public function name(): string
    {
        return Clearance::TAX;
    }

    public function assess(TaxableEvent $event): Outcome
    {
        if ($event instanceof Refund) {
            return $this->assess($event->original)->reversed($event->share());
        }

        return $this->pipeline->process(new Clearance($event, $this->table));
    }
}
