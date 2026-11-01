<?php

declare(strict_types=1);

use GeorgianTax\Domain\Event\Expense;
use GeorgianTax\Domain\Event\ExpenseNature;
use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\NonMarketDeal;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\SelfSupply;
use GeorgianTax\Domain\Event\SelfSupplyReason;
use GeorgianTax\Domain\Income\ExemptProfit;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Law\Article;
use GeorgianTax\ProfitTax\ProfitTax;
use GeorgianTax\Tests\Support\ProfitTaxCase;
use Mchekhashvili\Money\Money;

/** One test per row of the profit tax section of docs/CATALOGUE.md. */
uses(ProfitTaxCase::class);

/* ── Distributions ── */

test('dividend is taxed at 15 percent grossed up', function () {
    $report = $this->process($this->dividend($this->company, $this->owner, 850));

    $this->assertTaxed($report->for('profit'), '98¹.1', '15%', base: 1000, tax: 150);
    $this->assertTaxed($report->for('income'), '130.1', '5%', base: 850, tax: 42.5); // and the partner's withholding
});

test('dividend to a resident company is not a distribution', function () {
    $this->assertNotTaxable($this->profit($this->dividend($this->company, Party::company('holding'), 1000)), '98¹.2.გ');
});

test('dividend out of foreign dividends is exempt', function () {
    $event = new Pay($this->company, $this->owner, K::Dividend, Money::GEL(1000), $this->today, fromExemptProfit: ExemptProfit::ForeignDividends);

    $this->assertExempt($this->profit($event), '98¹.2.ე');
});

test('international company pays five percent', function () {
    $intl = Party::company('intl', Status::InternationalCompany);

    $this->assertTaxed($this->profit($this->dividend($intl, $this->owner, 950)), '23.10', '5%', base: 1000, tax: 50);
});

test('free zone company is exempt', function () {
    $this->assertExempt($this->profit($this->dividend(Party::company('fiz', Status::FreeIndustrialZone), $this->owner, 1000)), '99.1.ნ');
});

test('banks are taxed on annual profit not distributions', function () {
    $this->assertNotTaxable($this->profit($this->dividend(Party::company('bank', Status::CreditInstitution), $this->owner, 1000)), '97.12');
});

test('below market sale to a related party is a deemed distribution', function () {
    $deal = new NonMarketDeal($this->company, Party::person('brother'), Money::GEL(600), Money::GEL(1000), $this->today);

    $this->assertTaxed($this->profit($deal), '98¹.4', '15%', base: 400 / 0.85);
});

/* ── Expenses ── */

test('non business expense is taxed', function () {
    $this->assertTaxed($this->profit($this->expense(850, ExpenseNature::NotForProfit)), '98².1.ბ', '15%', base: 1000, tax: 150);
});

test('loan to an individual is taxed unless the lender is licensed', function () {
    $this->assertTaxed($this->profit($this->expense(850, ExpenseNature::LoanToIndividualOrNonResident)), '98².3.ზ', '15%');

    $bank = Party::company('mfo', Status::LicensedFinancialInstitution);
    $this->assertNotTaxable($this->profit(new Expense($bank, Money::GEL(850), $this->today, ExpenseNature::LoanToIndividualOrNonResident)), '98².3.ზ');
});

test('representation is taxed only above one percent of last years revenue', function () {
    $this->books->set($this->company, 2025, revenue: Money::GEL(100_000)); // limit 1,000

    $this->assertNotTaxable($this->process($this->expense(800, ExpenseNature::Representation))->for('profit'), '98⁴');
    $this->assertTaxed($this->profit($this->expense(500, ExpenseNature::Representation)), '98⁴', '15%', base: 300 / 0.85);
});

test('representation in the founding year waits for year end', function () {
    $this->books->founded($this->company, 2026);

    $this->assertInstanceOf(Unresolved::class, $this->profit($this->expense(500, ExpenseNature::Representation)));
});

/* ── Free supplies ── */

test('gift to a foreign company is taxed', function () {
    $this->assertTaxed($this->profit(new Gift($this->company, Party::foreignCompany('gmbh', 'DE'), Money::GEL(850), $this->today)), '98³', '15%', base: 1000);
});

test('gift to an individual is left to income tax', function () {
    $report = $this->process(new Gift($this->company, $this->owner, Money::GEL(5000), $this->today));

    $this->assertNotTaxable($report->for('profit'), '98³.3.ბ');
    $this->assertTaxed($report->for('income'), '154.1.მ', '20%');
});

test('gift to another resident company is not taxed', function () {
    $this->assertNotTaxable($this->profit(new Gift($this->company, Party::company('friend'), Money::GEL(1000), $this->today)), '98³.3.ზ');
});

test('charity is free up to ten percent of last years net profit', function () {
    $this->books->set($this->company, 2025, netProfit: Money::GEL(50_000)); // limit 5,000
    $charity = new Party('red-cross', PartyKind::Organization, statuses: [Status::CharityOrganization]);

    $this->assertNotTaxable($this->process(new Gift($this->company, $charity, Money::GEL(4000), $this->today))->for('profit'), '98³.3.ა');
    $this->assertTaxed($this->profit(new Gift($this->company, $charity, Money::GEL(2000), $this->today)), '98³', '15%');
});

test('inventory shortage is a free supply', function () {
    $this->assertTaxed($this->profit(new SelfSupply($this->company, Money::GEL(850), $this->today, SelfSupplyReason::Shortage)), '98³', '15%', base: 1000);
});

/* ── Scope ── */

test('income received is not taxed until distributed', function () {
    $this->assertNotTaxable($this->profit(new Pay($this->owner, $this->company, K::ServiceFee, Money::GEL(1000), $this->today)), '97.1');
});

test('foreign companys income is left to withholding', function () {
    $this->assertNotTaxable($this->profit(new Pay($this->company, Party::foreignCompany('gmbh', 'DE'), K::ServiceFee, Money::GEL(1000), $this->today)), '97.4');
});

test('every profit rule cites an article', function () {
    foreach (glob(dirname(__DIR__, 2) . '/src/ProfitTax/Rule/*.php') as $file) {
        $class = 'GeorgianTax\\ProfitTax\\Rule\\' . basename($file, '.php');
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInterface()) {
            $this->assertNotEmpty($reflection->getAttributes(Article::class), "$class has no #[Article].");
        }
    }
});
