<?php

declare(strict_types=1);

use GeorgianTax\Domain\Asset\Asset;
use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Event\Inherit;
use GeorgianTax\Domain\Event\Pay;
use GeorgianTax\Domain\Event\Refund;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Domain\Income\IncomeKind as K;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Regime;
use GeorgianTax\Domain\Party\Relief;
use GeorgianTax\Domain\Party\Residency;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Collection;
use GeorgianTax\Engine\Outcome\Taxable;
use GeorgianTax\Engine\Outcome\Unresolved;
use GeorgianTax\IncomeTax\IncomeTax;
use GeorgianTax\Law\Article;
use GeorgianTax\Tests\Support\IncomeTaxCase;
use Mchekhashvili\Money\Money;

/** One test per row of docs/CATALOGUE.md. */
uses(IncomeTaxCase::class);

/* ── Salary ── */

test('salary is withheld at twenty percent', function () {
    $report = $this->process($this->pay($this->company, $this->person, K::Salary, 3000));

    $this->assertTaxed($report->for('income'), '154.1.ა', '20%', Collection::WithheldAtSource, 'acme', 600);
    $this->assertSame(240000, $report->netToRecipient()->minor);
});

test('international company salary is five percent', function () {
    $employer = Party::company('intl', Status::InternationalCompany);

    $this->assertTaxed($this->income($this->pay($employer, $this->person, K::Salary, 1000)), '23.7', '5%', Collection::WithheldAtSource);
});

test('free zone salary is left to the government resolution', function () {
    $outcome = $this->income($this->pay(Party::company('fiz', Status::FreeIndustrialZone), $this->person, K::Salary, 1000));

    $this->assertInstanceOf(Unresolved::class, $outcome);
    $this->assertSame('153.9', $outcome->basis()->ref);
});

test('non resident employee is withheld under article 134', function () {
    $outcome = $this->income($this->pay($this->company, Party::foreigner('john', 'US'), K::Salary, 1000));

    $this->assertTaxed($outcome, '134.1.ვ', '20%', Collection::WithheldAtSource, 'acme');
});

test('short stay for foreign employer is exempt', function () {
    $event = new Pay(Party::foreignCompany('gmbh', 'DE'), Party::foreigner('hans', 'DE'), K::Salary, Money::GEL(5000), $this->today, daysWorkedInGeorgia: 20);

    $this->assertExempt($this->income($event), '82.1.შ');
});

test('foreign employer without pe leaves the employee to pay', function () {
    $outcome = $this->income($this->pay(Party::foreignCompany('gmbh', 'DE'), $this->person, K::Salary, 1000));

    $this->assertTaxed($outcome, '154.1.ა', '20%', Collection::MonthlyDeclaration);
});

test('small business first six thousand of salaries is not taxed', function () {
    $employer = Party::person('shop')->withKind(PartyKind::IndividualEntrepreneur)->with(Regime::SmallBusiness);

    $this->assertExempt($this->income($this->pay($employer, $this->person, K::Salary, 1500)), '94.4');
});

/* ── Services and rent ── */

test('service from an individual is withheld', function () {
    $this->assertTaxed($this->income($this->pay($this->company, $this->person, K::ServiceFee, 1000)), '154.1.დ', '20%', Collection::WithheldAtSource, 'acme');
});

test('vat registered individual declares service income', function () {
    $this->assertTaxed($this->income($this->pay($this->company, Party::person('vat', Status::VatPayer), K::ServiceFee, 1000)), '154.1.დ', '20%', Collection::AnnualDeclaration);
});

test('micro business pays no income tax', function () {
    $this->assertExempt($this->income($this->pay($this->company, $this->person->with(Regime::MicroBusiness), K::ServiceFee, 1000)), '86');
});

test('small business pays one percent', function () {
    $small = $this->person->withKind(PartyKind::IndividualEntrepreneur)->with(Regime::SmallBusiness);

    $this->assertTaxed($this->income($this->pay($this->company, $small, K::ServiceFee, 1000)), '90.1', '1%', Collection::MonthlyDeclaration, null, 10);
});

test('small business pays three percent above the threshold', function () {
    $small = $this->person->withKind(PartyKind::IndividualEntrepreneur)->with(Regime::SmallBusiness);
    $this->ledger->seed($small, null, K::ServiceFee, 2026, Money::GEL(499_000));

    $this->assertTaxed($this->income($this->pay($this->company, $small, K::ServiceFee, 5000)), '90.2', '3%', Collection::MonthlyDeclaration);
});

test('residential rent without deductions is five percent', function () {
    $event = new Pay($this->company, $this->person, K::ResidentialRent, Money::GEL(1000), $this->today, noDeductionsOnResidentialRent: true);

    $this->assertTaxed($this->income($event), '154.1.დ', '5%', Collection::WithheldAtSource);
});

test('rent from a private tenant is declared', function () {
    $this->assertTaxed($this->income($this->pay(Party::person('tenant'), $this->person, K::Rent, 800)), '154.1.დ', '20%', Collection::AnnualDeclaration);
});

/* ── Dividends and interest ── */

test('dividend is five percent', function () {
    $this->assertTaxed($this->income($this->pay($this->company, $this->person, K::Dividend, 1000)), '130.1', '5%', Collection::WithheldAtSource);
});

test('bank dividend from recent profits is exempt', function () {
    $event = new Pay(Party::company('bank', Status::CreditInstitution), $this->person, K::Dividend, Money::GEL(1000), $this->today, profitYear: 2024);

    $this->assertExempt($this->income($event), '130.8');
});

test('deposit interest from a bank is exempt', function () {
    $this->assertExempt($this->income($this->pay(Party::company('bank', Status::LicensedFinancialInstitution), $this->person, K::Interest, 100)), '131.5');
});

test('interest from a company is five percent', function () {
    $this->assertTaxed($this->income($this->pay($this->company, $this->person, K::Interest, 100)), '131.1', '5%', Collection::WithheldAtSource);
});

/* ── Non-residents ── */

test('foreign company serving a georgian company from abroad is withheld ten percent', function () {
    $event = new Pay($this->company, Party::foreignCompany('gmbh', 'DE'), K::ServiceFee, Money::GEL(1000), $this->today, Place::abroad('DE'));

    $this->assertTaxed($this->income($event), '134.1.ე', '10%', Collection::WithheldAtSource, 'acme', 100);
});

test('foreign pe abroad takes the service out of georgian source', function () {
    $event = new Pay($this->company, Party::foreignCompany('gmbh', 'DE'), K::ServiceFee, Money::GEL(1000), $this->today, Place::abroad('DE', providerProvedForeignPE: true));

    $this->assertNotTaxable($this->income($event), '79.ბ');
});

test('preferential tax country is fifteen percent', function () {
    $offshore = Party::foreignCompany('ltd', 'VG', Status::PreferentialTaxJurisdiction);
    $event = new Pay($this->company, $offshore, K::ServiceFee, Money::GEL(1000), $this->today, Place::abroad('VG'));

    $this->assertTaxed($this->income($event), '134.1.ე', '15%', Collection::WithheldAtSource);
});

test('non resident royalty is five percent', function () {
    $this->assertTaxed($this->income($this->pay($this->company, Party::foreignCompany('ip', 'NL'), K::Royalty, 1000)), '134.1.ბ¹', '5%', Collection::WithheldAtSource);
});

test('international transport is ten percent', function () {
    $this->assertTaxed($this->income($this->pay($this->company, Party::foreignCompany('ship', 'TR'), K::InternationalTransport, 1000)), '134.1.გ', '10%', Collection::WithheldAtSource);
});

test('non resident with a georgian pe is not withheld', function () {
    $withPe = new Party('branch', PartyKind::Company, Residency::NonResident, 'DE', hasGeorgianPE: true);

    $this->assertNotTaxable($this->income($this->pay($this->company, $withPe, K::ServiceFee, 1000)), '134.1');
});

test('non resident selling a georgian flat declares the gain', function () {
    $flat = new Asset(AssetType::Dwelling, new DateTimeImmutable('2025-06-01'), Money::GEL(100_000));
    $sale = new Sell(Party::foreigner('john', 'US'), $this->person, $flat, Money::GEL(120_000), $this->today);

    $this->assertTaxed($this->income($sale), '80.4', '5%', Collection::AnnualDeclaration, null, 1000);
});

/* ── Scope ── */

test('resident company pays profit tax not income tax', function () {
    $this->assertNotTaxable($this->income($this->pay($this->company, Party::company('client'), K::ServiceFee, 1000)), '96');
});

test('residents foreign source income is exempt', function () {
    $event = new Pay(Party::foreignCompany('gmbh', 'DE'), $this->person, K::ServiceFee, Money::GEL(1000), $this->today, Place::abroad('DE'));

    $this->assertExempt($this->income($event), '82.1.ფ');
});

/* ── Selling assets ── */

test('flat held over two years is exempt', function () {
    $this->assertExempt($this->income($this->sell(AssetType::Dwelling, '2023-01-01', 100_000, 150_000)), '82.1.ვ.ა');
});

test('flat held under two years is five percent of the gain', function () {
    $this->assertTaxed($this->income($this->sell(AssetType::Dwelling, '2025-06-01', 100_000, 150_000)), '81.3', '5%', Collection::MonthlyDeclaration, null, 2500);
});

test('car held under six months is five percent', function () {
    $this->assertTaxed($this->income($this->sell(AssetType::Car, '2026-08-01', 20_000, 22_000)), '81.3', '5%', Collection::MonthlyDeclaration, null, 100);
});

test('shares held under two years are twenty percent monthly', function () {
    $this->assertTaxed($this->income($this->sell(AssetType::Shares, '2026-01-01', 10_000, 15_000)), '153.12', '20%', Collection::MonthlyDeclaration, null, 1000);
});

test('sale at a loss is not taxable', function () {
    $this->assertNotTaxable($this->income($this->sell(AssetType::Shares, '2026-01-01', 10_000, 8_000)), '82.4.ბ');
});

/* ── Gifts and inheritance ── */

test('gift from a parent is exempt', function () {
    $this->assertExempt($this->income(new Gift(Party::person('mum'), $this->person, Money::GEL(50_000), $this->today, HeirOrder::First)), '82.1.ზ');
});

test('gifts over one thousand a year are declared', function () {
    $this->assertExempt($this->process(new Gift(Party::person('friend'), $this->person, Money::GEL(800), $this->today))->for('income'), '82.1.თ');

    $second = $this->income(new Gift(Party::person('friend'), $this->person, Money::GEL(500), $this->today));

    $this->assertTaxed($second, '80.1, 153.6', '20%', Collection::AnnualDeclaration);
});

test('gift from an employer is withheld', function () {
    $gift = new Gift($this->company, $this->person, Money::GEL(500), $this->today, fromEmployer: true);

    $this->assertTaxed($this->income($gift), '154.1.მ', '20%', Collection::WithheldAtSource, 'acme');
});

test('inheritance from an aunt above 150k is paid over two years', function () {
    $this->assertExempt($this->income(new Inherit($this->person, Money::GEL(100_000), $this->today, HeirOrder::Fourth)), '82.1.ი');
    $this->assertTaxed($this->income(new Inherit($this->person, Money::GEL(200_000), $this->today, HeirOrder::Fourth)), '153.7', '20%', Collection::OverTwoYears);
});

/* ── Winnings, pensions, other ── */

test('small lottery win is exempt large one withheld', function () {
    $lottery = Party::company('lotto');

    $this->assertExempt($this->income($this->pay($lottery, $this->person, K::LotteryWin, 800)), '82.1.ღ');
    $this->assertTaxed($this->income($this->pay($lottery, $this->person, K::LotteryWin, 5000)), '154.1.ე', '20%', Collection::WithheldAtSource);
});

test('gaming withdrawal is five percent but exempt for foreigners', function () {
    $casino = Party::company('casino');

    $this->assertTaxed($this->income($this->pay($casino, $this->person, K::GamingAccountWithdrawal, 1000)), '154.1.ე¹', '5%', Collection::WithheldAtSource);
    $this->assertExempt($this->income($this->pay($casino, new Party('tom', PartyKind::Individual, citizenship: 'GB'), K::GamingAccountWithdrawal, 1000)), '82.1.ძ¹');
});

test('state pension is exempt private pension withheld', function () {
    $this->assertExempt($this->income($this->pay(Party::state(), $this->person, K::StatePension, 350)), '82.1.ბ');
    $this->assertTaxed($this->income($this->pay(Party::company('fund', Status::PensionProvider), $this->person, K::Pension, 500)), '154.1.ბ', '20%', Collection::WithheldAtSource);
});

test('alimony is exempt', function () {
    $this->assertExempt($this->income($this->pay(Party::person('ex'), $this->person, K::Alimony, 500)), '82.1.დ');
});

test('tourist enterprise payout is five percent', function () {
    $hotel = Party::company('hotel', Status::TouristEnterprise);

    $this->assertTaxed($this->income($this->pay($hotel, $this->person, K::TouristHotelPayout, 2000)), '133².1', '5%', Collection::WithheldAtSource);
});

test('listed goods without waybill are three percent', function () {
    $event = new Pay($this->company, $this->person, K::GoodsSupply, Money::GEL(1000), $this->today, listedGoods: true);

    $this->assertTaxed($this->income($event), '133³.1', '3%', Collection::WithheldAtSource);
});

test('broker withholds on securities gain', function () {
    $shares = new Asset(AssetType::Shares, new DateTimeImmutable('2026-01-01'), Money::GEL(1000));
    $sale = new Sell($this->person, Party::person('buyer'), $shares, Money::GEL(1500), $this->today, Party::company('broker', Status::Broker));

    $this->assertTaxed($this->income($sale), '154.1.ლ', '20%', Collection::WithheldAtSource, 'broker', 100);
});

/* ── Allowances and refunds ── */

test('single parent allowance shelters 3000 a year', function () {
    $parent = Party::person('mother', Relief::SingleParent);

    $first = $this->process($this->pay($this->company, $parent, K::Salary, 2000))->for('income');
    $second = $this->income($this->pay($this->company, $parent, K::Salary, 3000));

    $this->assertSame(0, $first->amount->minor);
    $this->assertSame(200000, $second->base->minor);   // 1,000 of the 3,000 still sheltered
    $this->assertSame(40000, $second->amount->minor);
});

test('refund reverses in proportion', function () {
    $salary = $this->pay($this->company, $this->person, K::Salary, 3000);
    $refund = $this->income(new Refund($salary, $this->today, Money::GEL(1500)));

    $this->assertInstanceOf(Taxable::class, $refund);
    $this->assertSame(-30000, $refund->amount->minor);
});

/* ── Traceability ── */

test('every rule exemption and source rule cites an article', function () {
    $root = dirname(__DIR__, 2) . '/src/IncomeTax';
    $checked = 0;

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->getExtension() !== 'php' || !preg_match('#/(Rule|Source)/#', $file->getPathname())) {
            continue;
        }

        $class = 'GeorgianTax\\IncomeTax\\' . str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($root) + 1));
        $reflection = new ReflectionClass($class);

        if ($reflection->isInterface()) {
            continue;
        }

        $this->assertNotEmpty($reflection->getAttributes(Article::class), "$class has no #[Article].");
        $checked++;
    }

    $this->assertGreaterThan(20, $checked);
});
