<?php

declare(strict_types=1);

namespace GeorgianTax\Tests;

use DateTimeImmutable;
use GeorgianTax\Domain\Event\Expense;
use GeorgianTax\Domain\Event\ExpenseNature;
use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\NonMarketDeal;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\SelfSupply;
use GeorgianTax\Domain\Event\SelfSupplyReason;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\ExemptProfit;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Money;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\Engine\Outcome\NotTaxable;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\Outcome\Unresolved;
use GeorgianTax\Engine\TaxEngine;
use GeorgianTax\Engine\TaxReport;
use GeorgianTax\IncomeTax\IncomeTax;
use GeorgianTax\Law\Article;
use GeorgianTax\Ledger\InMemoryLedger;
use GeorgianTax\Ledger\StaticBooks;
use GeorgianTax\ProfitTax\ProfitTax;
use GeorgianTax\Rates\ConfigRateTable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** One test per row of the profit tax section of docs/CATALOGUE.md. */
final class ProfitTaxTest extends TestCase
{
    private InMemoryLedger $ledger;
    private StaticBooks $books;
    private TaxEngine $engine;
    private DateTimeImmutable $today;
    private Party $company;
    private Party $owner;

    protected function setUp(): void
    {
        $this->ledger = new InMemoryLedger();
        $this->books = new StaticBooks();
        $rates = ConfigRateTable::standard();
        $this->engine = new TaxEngine(
            $this->ledger,
            IncomeTax::standard($this->ledger, $rates),
            ProfitTax::standard($this->ledger, $rates, $this->books),
        );
        $this->today = new DateTimeImmutable('2026-10-30');
        $this->company = Party::company('acme');
        $this->owner = Party::person('nino');
    }

    /* ── Distributions ── */

    public function testDividendIsTaxedAt15PercentGrossedUp(): void
    {
        $report = $this->process($this->dividend($this->company, $this->owner, 850));

        $this->assertTaxed($report->for('profit'), '98¹.1', '15%', base: 1000, tax: 150);
        $this->assertTaxed($report->for('income'), '130.1', '5%', base: 850, tax: 42.5); // and the partner's withholding
    }

    public function testDividendToAResidentCompanyIsNotADistribution(): void
    {
        $this->assertNotTaxable($this->profit($this->dividend($this->company, Party::company('holding'), 1000)), '98¹.2.გ');
    }

    public function testDividendOutOfForeignDividendsIsExempt(): void
    {
        $event = new Pay($this->company, $this->owner, K::Dividend, Money::gel(1000), $this->today, fromExemptProfit: ExemptProfit::ForeignDividends);

        $this->assertExempt($this->profit($event), '98¹.2.ე');
    }

    public function testInternationalCompanyPaysFivePercent(): void
    {
        $intl = Party::company('intl', Status::InternationalCompany);

        $this->assertTaxed($this->profit($this->dividend($intl, $this->owner, 950)), '23.10', '5%', base: 1000, tax: 50);
    }

    public function testFreeZoneCompanyIsExempt(): void
    {
        $this->assertExempt($this->profit($this->dividend(Party::company('fiz', Status::FreeIndustrialZone), $this->owner, 1000)), '99.1.ნ');
    }

    public function testBanksAreTaxedOnAnnualProfitNotDistributions(): void
    {
        $this->assertNotTaxable($this->profit($this->dividend(Party::company('bank', Status::CreditInstitution), $this->owner, 1000)), '97.12');
    }

    public function testBelowMarketSaleToARelatedPartyIsADeemedDistribution(): void
    {
        $deal = new NonMarketDeal($this->company, Party::person('brother'), Money::gel(600), Money::gel(1000), $this->today);

        $this->assertTaxed($this->profit($deal), '98¹.4', '15%', base: 400 / 0.85);
    }

    /* ── Expenses ── */

    public function testNonBusinessExpenseIsTaxed(): void
    {
        $this->assertTaxed($this->profit($this->expense(850, ExpenseNature::NotForProfit)), '98².1.ბ', '15%', base: 1000, tax: 150);
    }

    public function testLoanToAnIndividualIsTaxedUnlessTheLenderIsLicensed(): void
    {
        $this->assertTaxed($this->profit($this->expense(850, ExpenseNature::LoanToIndividualOrNonResident)), '98².3.ზ', '15%');

        $bank = Party::company('mfo', Status::LicensedFinancialInstitution);
        $this->assertNotTaxable($this->profit(new Expense($bank, Money::gel(850), $this->today, ExpenseNature::LoanToIndividualOrNonResident)), '98².3.ზ');
    }

    public function testRepresentationIsTaxedOnlyAboveOnePercentOfLastYearsRevenue(): void
    {
        $this->books->set($this->company, 2025, revenue: Money::gel(100_000)); // limit 1,000

        $this->assertNotTaxable($this->process($this->expense(800, ExpenseNature::Representation))->for('profit'), '98⁴');
        $this->assertTaxed($this->profit($this->expense(500, ExpenseNature::Representation)), '98⁴', '15%', base: 300 / 0.85);
    }

    public function testRepresentationInTheFoundingYearWaitsForYearEnd(): void
    {
        $this->books->founded($this->company, 2026);

        $this->assertInstanceOf(Unresolved::class, $this->profit($this->expense(500, ExpenseNature::Representation)));
    }

    /* ── Free supplies ── */

    public function testGiftToAForeignCompanyIsTaxed(): void
    {
        $this->assertTaxed($this->profit(new Gift($this->company, Party::foreignCompany('gmbh', 'DE'), Money::gel(850), $this->today)), '98³', '15%', base: 1000);
    }

    public function testGiftToAnIndividualIsLeftToIncomeTax(): void
    {
        $report = $this->process(new Gift($this->company, $this->owner, Money::gel(5000), $this->today));

        $this->assertNotTaxable($report->for('profit'), '98³.3.ბ');
        $this->assertTaxed($report->for('income'), '154.1.მ', '20%');
    }

    public function testGiftToAnotherResidentCompanyIsNotTaxed(): void
    {
        $this->assertNotTaxable($this->profit(new Gift($this->company, Party::company('friend'), Money::gel(1000), $this->today)), '98³.3.ზ');
    }

    public function testCharityIsFreeUpToTenPercentOfLastYearsNetProfit(): void
    {
        $this->books->set($this->company, 2025, netProfit: Money::gel(50_000)); // limit 5,000
        $charity = new Party('red-cross', PartyKind::Organization, statuses: [Status::CharityOrganization]);

        $this->assertNotTaxable($this->process(new Gift($this->company, $charity, Money::gel(4000), $this->today))->for('profit'), '98³.3.ა');
        $this->assertTaxed($this->profit(new Gift($this->company, $charity, Money::gel(2000), $this->today)), '98³', '15%');
    }

    public function testInventoryShortageIsAFreeSupply(): void
    {
        $this->assertTaxed($this->profit(new SelfSupply($this->company, Money::gel(850), $this->today, SelfSupplyReason::Shortage)), '98³', '15%', base: 1000);
    }

    /* ── Scope ── */

    public function testIncomeReceivedIsNotTaxedUntilDistributed(): void
    {
        $this->assertNotTaxable($this->profit(new Pay($this->owner, $this->company, K::ServiceFee, Money::gel(1000), $this->today)), '97.1');
    }

    public function testForeignCompanysIncomeIsLeftToWithholding(): void
    {
        $this->assertNotTaxable($this->profit(new Pay($this->company, Party::foreignCompany('gmbh', 'DE'), K::ServiceFee, Money::gel(1000), $this->today)), '97.4');
    }

    public function testEveryProfitRuleCitesAnArticle(): void
    {
        foreach (glob(dirname(__DIR__) . '/src/ProfitTax/Rule/*.php') as $file) {
            $class = 'GeorgianTax\\ProfitTax\\Rule\\' . basename($file, '.php');
            $reflection = new ReflectionClass($class);

            if (!$reflection->isInterface()) {
                $this->assertNotEmpty($reflection->getAttributes(Article::class), "$class has no #[Article].");
            }
        }
    }

    /* ── Helpers ── */

    private function dividend(Party $company, Party $partner, int $gel): Pay
    {
        return new Pay($company, $partner, K::Dividend, Money::gel($gel), $this->today);
    }

    private function expense(int $gel, ExpenseNature $nature): Expense
    {
        return new Expense($this->company, Money::gel($gel), $this->today, $nature);
    }

    private function process(TaxableEvent $event): TaxReport
    {
        return $this->engine->process($event);
    }

    private function profit(TaxableEvent $event): Outcome
    {
        return $this->process($event)->for('profit');
    }

    private function assertTaxed(Outcome $outcome, string $article, string $rate, ?float $base = null, ?float $tax = null): void
    {
        $this->assertInstanceOf(Taxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
        $this->assertSame($rate, (string) $outcome->rate, (string) $outcome);

        if ($base !== null) {
            $this->assertSame(Money::gel($base)->tetri, $outcome->base->tetri, (string) $outcome);
        }

        if ($tax !== null) {
            $this->assertSame(Money::gel($tax)->tetri, $outcome->amount->tetri, (string) $outcome);
        }
    }

    private function assertExempt(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(Exempt::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }

    private function assertNotTaxable(Outcome $outcome, string $article): void
    {
        $this->assertInstanceOf(NotTaxable::class, $outcome, (string) $outcome);
        $this->assertSame($article, $outcome->basis()->ref, (string) $outcome);
    }
}
