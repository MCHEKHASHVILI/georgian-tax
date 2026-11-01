<?php

declare(strict_types=1);

namespace GeorgianTax\Tests\Support;

use DateTimeImmutable;
use GeorgianTax\Domain\Asset\Asset;
use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\Engine\Outcome\NotTaxable;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\Engine\TaxReport;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Tests\TestCase;
use Mchekhashvili\Money\Money;

/** Setup and assertions shared by tests/Taxes/IncomeTaxTest.php. */
abstract class IncomeTaxCase extends TestCase
{
    protected InMemoryLedger $ledger;
    protected TaxEngine $engine;
    protected DateTimeImmutable $today;
    protected Party $company;
    protected Party $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = $this->memoryLedger();
        $this->engine = $this->taxEngine('income');
        $this->today = new DateTimeImmutable('2026-10-30');
        $this->company = Party::company('acme');
        $this->person = Party::person('nino');
    }

    protected function pay(Party $payer, Party $payee, K $kind, int $gel): Pay
    {
        return new Pay($payer, $payee, $kind, Money::GEL($gel), $this->today);
    }

    protected function sell(AssetType $type, string $acquired, int $cost, int $price): Sell
    {
        return new Sell($this->person, Party::person('buyer'), new Asset($type, new DateTimeImmutable($acquired), Money::GEL($cost)), Money::GEL($price), $this->today);
    }

    protected function process(TaxableEvent $event): TaxReport
    {
        return $this->engine->process($event);
    }

    protected function income(TaxableEvent $event): Outcome
    {
        return $this->process($event)->for('income');
    }

    protected function assertTaxed(Outcome $outcome, string $article, string $rate, Collection $collection, ?string $agent = null, ?int $taxGel = null): void
    {
        $this->assertInstanceOf(Taxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
        $this->assertSame($rate, (string) $outcome->rate, (string) $outcome);
        $this->assertSame($collection, $outcome->collection, (string) $outcome);

        if ($agent !== null) {
            $this->assertSame($agent, $outcome->agent?->id, (string) $outcome);
        }

        if ($taxGel !== null) {
            $this->assertSame($taxGel * 100, $outcome->amount->minor, (string) $outcome);
        }
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
