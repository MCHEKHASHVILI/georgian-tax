<?php

declare(strict_types=1);

namespace GeorgianTax\Tests\Support;

use DateTimeImmutable;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Goods\ImportRelief;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\Engine\Outcome\Levied;
use GeorgianTax\Engine\Outcome\NotTaxable;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\Tests\TestCase;
use Mchekhashvili\Money\Money;

/** Setup and assertions shared by tests/Taxes/ExciseTest.php. */
abstract class ExciseCase extends TestCase
{
    protected TaxEngine $engine;
    protected DateTimeImmutable $today;
    protected Party $importer;
    protected Party $producer;
    protected Party $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = $this->taxEngine('excise', 'vat');
        $this->today = new DateTimeImmutable('2026-10-30');
        $this->importer = Party::company('importer', Status::VatPayer);
        $this->producer = Party::company('distillery', Status::VatPayer, Status::ExciseProducer);
        $this->shop = Party::company('shop', Status::VatPayer);
    }

    protected function import(Commodity $c, int $customs = 10_000, ?ImportRelief $relief = null): Import
    {
        return new Import($this->importer, Money::GEL($customs), $this->today, commodity: $c, relief: $relief);
    }

    protected function sell(Party $seller, Commodity $c): Pay
    {
        return new Pay($this->shop === $seller ? Party::person('customer') : $this->shop, $seller, K::GoodsSupply, Money::GEL(1000), $this->today, commodity: $c);
    }

    protected function car(?int $engineCc = null, ?int $yearMade = null, bool $hybrid = false, bool $electric = false,
        bool $rightHandDrive = false, bool $sportsCar = false, bool $classicCar = false): Import
    {
        return $this->import(new Commodity('8703 23', 1, engineCc: $engineCc, yearMade: $yearMade, hybrid: $hybrid,
            electric: $electric, rightHandDrive: $rightHandDrive, sportsCar: $sportsCar, classicCar: $classicCar));
    }

    protected function excise(object $event): Outcome
    {
        return $this->engine->process($event)->for('excise');
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
