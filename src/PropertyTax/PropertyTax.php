<?php

declare(strict_types=1);

namespace GeorgianTax\PropertyTax;

use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Pipeline\Pipeline;
use GeorgianTax\Engine\Tax;
use GeorgianTax\PropertyTax\Rule as R;
use GeorgianTax\PropertyTax\Stage;
use LogicException;

/**
 * Property tax — the local tax — as one stage of the engine, and a pipeline of its own:
 *
 *   IdentifyTaxpayer  Art. 201, 203  who pays on what
 *   ApplyExemptions   Art. 206       by owner, by item, and the 206.2 leasing carve-out
 *   rate rules        Art. 202, 204  land per hectare or m²; company and individual property by value
 *
 * Feed it an OwnProperty event per item per year. Rates are municipal: pass the council's
 * decisions as MunicipalRates, or take the Code's caps and base rates.
 */
final class PropertyTax extends Tax
{
    /** @param array<string, mixed> $land */
    public function __construct(
        private readonly Pipeline $pipeline,
        private readonly array $land,
        private readonly MunicipalRates $rates,
    ) {}

    public static function standard(?MunicipalRates $rates = null): self
    {
        /** @var list<R\Rule> $rules */
        $rules = [new R\AgriculturalLand(), new R\OtherLand(), new R\CompanyProperty(), new R\IndividualProperty()];

        $assess = static function (Holding $h) use ($rules): Outcome {
            foreach ($rules as $rule) {
                if (($outcome = $rule->assess($h)) !== null) {
                    return $outcome;
                }
            }

            throw new LogicException('Every holding is land, a company\'s or an individual\'s.');
        };

        return new self(
            new Pipeline([new Stage\IdentifyTaxpayer(), new Stage\ApplyExemptions()], $assess),
            require __DIR__ . '/../../config/property.php',
            $rates ?? new MunicipalRates(),
        );
    }

    public function name(): string
    {
        return Holding::TAX;
    }

    public function assess(TaxableEvent $event): Outcome
    {
        return $this->pipeline->process(new Holding($event, $this->land, $this->rates));
    }
}
