<?php

declare(strict_types=1);

namespace GeorgianTax\Tests\Support;

use GeorgianTax\Domain\Event\OwnProperty;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Property\PropertyItem;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\Engine\Outcome\Levied;
use GeorgianTax\Engine\Outcome\NotTaxable;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\PropertyTax\MunicipalRates;
use GeorgianTax\Tests\TestCase;
use Mchekhashvili\Money\Money;

/** Setup and assertions shared by tests/Taxes/PropertyTaxTest.php. */
abstract class PropertyTaxCase extends TestCase
{
    protected TaxEngine $engine;
    protected Party $company;
    protected Party $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = $this->engineWith();
        $this->company = Party::company('acme');
        $this->person = Party::person('nino');
    }

    /** The engine, after binding the councils' rates when given. */
    protected function engineWith(?MunicipalRates $rates = null): TaxEngine
    {
        if ($rates !== null) {
            $this->app->instance(MunicipalRates::class, $rates);
            $this->app->forgetInstance(TaxEngine::class);
        }

        return $this->taxEngine();
    }

    protected function own(Party $owner, PropertyItem $item, int $months = 12, ?int $income = null): OwnProperty
    {
        return new OwnProperty($owner, $item, 2026, $months, $income === null ? null : Money::GEL($income));
    }

    protected function property(object $event): Outcome
    {
        return $this->engine->process($event)->for('property');
    }

    protected function assertTaxed(Outcome $outcome, string $article, int $gel): void
    {
        $this->assertInstanceOf(Taxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
        $this->assertSame($gel * 100, $outcome->amount->minor, (string) $outcome);
    }

    protected function assertLevied(Outcome $outcome, string $article, int|float $gel): void
    {
        $this->assertInstanceOf(Levied::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
        $this->assertSame((int) round($gel * 100), $outcome->amount->minor, (string) $outcome);
    }

    protected function assertExempt(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(Exempt::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }

    protected function assertNotTaxable(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(NotTaxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }
}
