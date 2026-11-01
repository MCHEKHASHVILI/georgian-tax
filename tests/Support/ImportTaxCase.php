<?php

declare(strict_types=1);

namespace GeorgianTax\Tests\Support;

use DateTimeImmutable;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Goods\ImportRelief;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\TaxEngine;
use GeorgianTax\Tests\TestCase;
use Mchekhashvili\Money\Money;

/** Setup and assertions shared by tests/Taxes/ImportTaxTest.php. */
abstract class ImportTaxCase extends TestCase
{
    protected TaxEngine $engine;
    protected DateTimeImmutable $today;
    protected Party $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = $this->taxEngine('import', 'excise', 'vat');
        $this->today = new DateTimeImmutable('2026-10-30');
        $this->importer = Party::company('importer', Status::VatPayer);
    }

    protected function import(Commodity $c, int $customs = 10_000, ?ImportRelief $relief = null, ?float $eur = null): Import
    {
        return new Import($this->importer, Money::GEL($customs), $this->today, commodity: $c, relief: $relief, eurToGel: $eur);
    }

    protected function tax(object $event): Outcome
    {
        return $this->engine->process($event)->for('import');
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
