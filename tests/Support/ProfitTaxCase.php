<?php

declare(strict_types=1);

namespace GeorgianTax\Tests\Support;

use DateTimeImmutable;
use GeorgianTax\Domain\Event\Expense;
use GeorgianTax\Domain\Event\ExpenseNature;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Party\Party;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\TaxEngine;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use GeorgianTax\Ledger\CompanyBooks;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Ledger\StaticBooks;
use GeorgianTax\Tests\TestCase;
use Mchekhashvili\Money\Money;

/** Setup and assertions shared by tests/Taxes/ProfitTaxTest.php. */
abstract class ProfitTaxCase extends TestCase
{
    protected InMemoryLedger $ledger;
    protected StaticBooks $books;
    protected TaxEngine $engine;
    protected DateTimeImmutable $today;
    protected Party $company;
    protected Party $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = $this->memoryLedger();
        $this->books = new StaticBooks();
        $this->app->instance(CompanyBooks::class, $this->books);
        $this->engine = $this->taxEngine('income', 'profit');
        $this->today = new DateTimeImmutable('2026-10-30');
        $this->company = Party::company('acme');
        $this->owner = Party::person('nino');
    }

    protected function dividend(Party $company, Party $partner, int $gel): Pay
    {
        return new Pay($company, $partner, K::Dividend, Money::GEL($gel), $this->today);
    }

    protected function expense(int $gel, ExpenseNature $nature): Expense
    {
        return new Expense($this->company, Money::GEL($gel), $this->today, $nature);
    }

    protected function process(TaxableEvent $event): TaxReport
    {
        return $this->engine->process($event);
    }

    protected function profit(TaxableEvent $event): Outcome
    {
        return $this->process($event)->for('profit');
    }

    protected function assertTaxed(Outcome $outcome, string $article, string $rate, ?float $base = null, ?float $tax = null): void
    {
        $this->assertInstanceOf(Taxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
        $this->assertSame($rate, (string) $outcome->rate, (string) $outcome);

        if ($base !== null) {
            $this->assertSame(Money::GEL($base)->minor, $outcome->base->minor, (string) $outcome);
        }

        if ($tax !== null) {
            $this->assertSame(Money::GEL($tax)->minor, $outcome->amount->minor, (string) $outcome);
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
