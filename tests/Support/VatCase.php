<?php

declare(strict_types=1);

namespace GeorgianTax\Tests\Support;

use DateTimeImmutable;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\Engine\Outcome\ExemptWithCredit;
use GeorgianTax\Engine\Outcome\NotTaxable;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\Engine\TaxReport;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Tests\TestCase;
use Mchekhashvili\Money\Money;

/** Setup and assertions shared by tests/Taxes/VatTest.php. */
abstract class VatCase extends TestCase
{
    protected InMemoryLedger $ledger;
    protected TaxEngine $engine;
    protected DateTimeImmutable $today;
    protected Party $seller;
    protected Party $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = $this->memoryLedger();
        $this->engine = $this->taxEngine('income', 'vat');
        $this->today = new DateTimeImmutable('2026-10-30');
        $this->seller = Party::company('seller', Status::VatPayer);
        $this->buyer = Party::company('buyer', Status::VatPayer);
    }

    protected function pay(Party $customer, Party $supplier, K $kind, int $gel): Pay
    {
        return new Pay($customer, $supplier, $kind, Money::GEL($gel), $this->today);
    }

    protected function process(TaxableEvent $event): TaxReport
    {
        return $this->engine->process($event);
    }

    protected function vat(TaxableEvent $event): Outcome
    {
        return $this->process($event)->for('vat');
    }

    protected function assertCharged(Outcome $outcome, string $article, Collection $collection, ?int $vatGel = null): void
    {
        $this->assertInstanceOf(Taxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
        $this->assertSame('18%', (string) $outcome->rate, (string) $outcome);
        $this->assertSame($collection, $outcome->collection, (string) $outcome);

        if ($vatGel !== null) {
            $this->assertSame($vatGel * 100, $outcome->amount->minor, (string) $outcome);
        }
    }

    protected function assertExempt(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(Exempt::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }

    protected function assertExemptWithCredit(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(ExemptWithCredit::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }

    protected function assertNotTaxable(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(NotTaxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }
}
