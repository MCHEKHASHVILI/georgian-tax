<?php

declare(strict_types=1);

use GeorgianTax\Domain\Event\CloseGamblingMonth;
use GeorgianTax\Domain\Event\CloseYear;
use GeorgianTax\Domain\Event\GamblingActivity as G;
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
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
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
    $this->books->set($this->company, 2025, revenue: Money::GEL(100_000), costs: Money::GEL(90_000)); // 98⁴.2: limit 1,000

    $this->assertNotTaxable($this->process($this->expense(800, ExpenseNature::Representation))->for('profit'), '98⁴');
    $this->assertTaxed($this->profit($this->expense(500, ExpenseNature::Representation)), '98⁴', '15%', base: 300 / 0.85);
});

test('representation is measured against last years costs when they exceeded revenue', function () {
    $this->books->set($this->company, 2025, revenue: Money::GEL(100_000), costs: Money::GEL(150_000)); // 98⁴.2: limit 1,500

    $within = $this->process($this->expense(1200, ExpenseNature::Representation))->for('profit');
    $this->assertNotTaxable($within, '98⁴');
    expect($within->reason)->toContain("1500.00 GEL (1% of last year's costs)");

    $over = $this->profit($this->expense(500, ExpenseNature::Representation));
    $this->assertTaxed($over, '98⁴', '15%', base: 200 / 0.85);
    expect($over->notes)->toContain("Only the 200.00 GEL above this year's limit of 1500.00 GEL (1% of last year's costs) is taxed.");
});

test('representation with costs equal to revenue is measured against revenue', function () {
    $this->books->set($this->company, 2025, revenue: Money::GEL(100_000), costs: Money::GEL(100_000)); // 98⁴.2: costs do not exceed revenue

    expect($this->profit($this->expense(1000, ExpenseNature::Representation))->reason)->toContain("(1% of last year's revenue)");
});

test('representation within one known figure needs no other', function () {
    // 98⁴.2: the limit is 1% of the larger of the two, so 1% of either is at least the limit's floor
    $this->books->set($this->company, 2025, revenue: Money::GEL(100_000));
    $this->assertNotTaxable($this->profit($this->expense(500, ExpenseNature::Representation)), '98⁴');

    $this->books->set($this->company, 2025, costs: Money::GEL(100_000)); // revenue now unknown; 1,000 spent in all
    $this->assertNotTaxable($this->profit($this->expense(500, ExpenseNature::Representation)), '98⁴');
});

test('representation over one known figure waits for the other', function () {
    $this->books->set($this->company, 2025, revenue: Money::GEL(100_000)); // costs not given

    $over = $this->profit($this->expense(1500, ExpenseNature::Representation));
    expect($over)->toBeInstanceOf(Unresolved::class)
        ->and($over->basis()->ref)->toBe('98⁴')
        ->and($over->reason)->toContain("if last year's costs exceeded it");

    $this->books->set($this->company, 2025, costs: Money::GEL(100_000)); // revenue not given
    expect($this->profit($this->expense(1500, ExpenseNature::Representation)))->toBeInstanceOf(Unresolved::class);
});

test('representation with no figures for last year is unresolved', function () {
    $outcome = $this->profit($this->expense(100, ExpenseNature::Representation));

    expect($outcome)->toBeInstanceOf(Unresolved::class)
        ->and($outcome->reason)->toContain('or of its costs if they exceeded it');
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

/* ── Banks and lenders: the year's profit (Art. 97.12, 98.4) ── */

test('a bank pays twenty percent of the years gross income less deductions', function () {
    $bank = Party::company('bank', Status::CreditInstitution);
    $outcome = $this->profit(new CloseYear($bank, 2026, Money::GEL(5_000_000), Money::GEL(3_000_000)));

    $this->assertTaxed($outcome, '98.4', '20%', base: 2_000_000, tax: 400_000); // not grossed up: 97.10 is for 97.1–3
    expect($outcome->collection)->toBe(Collection::AnnualDeclaration)
        ->and($outcome->due->format('Y-m-d'))->toBe('2027-04-01') // 153.1, 62.2
        ->and($outcome->taxpayer)->toBe($bank)
        ->and($outcome->turnover())->toBeNull();
});

test('a microfinance organization or lender is taxed on the year too', function () {
    $mfo = Party::company('mfo', Status::CreditInstitution, Status::LicensedFinancialInstitution);

    $this->assertTaxed($this->profit(new CloseYear($mfo, 2025, Money::GEL(800), Money::GEL(300))), '98.4', '20%', base: 500, tax: 100);
});

test('earlier losses still carried are set against the years profit', function () {
    $bank = Party::company('bank', Status::CreditInstitution);
    $outcome = $this->profit(new CloseYear($bank, 2026, Money::GEL(1_000), Money::GEL(400), lossesCarried: Money::GEL(250)));

    $this->assertTaxed($outcome, '98.4', '20%', base: 350, tax: 70);
    expect(implode(' ', $outcome->notes))->toContain('250.00 GEL (121.4)');
});

test('losses that cover the whole profit leave nothing to tax and the rest carried', function () {
    $bank = Party::company('bank', Status::CreditInstitution);
    $outcome = $this->profit(new CloseYear($bank, 2026, Money::GEL(1_000), Money::GEL(400), lossesCarried: Money::GEL(900)));

    $this->assertNotTaxable($outcome, '121.4');
    expect($outcome->reason)->toContain('300.00 GEL still carried');
});

test('a year whose deductions exceed gross income is not taxed and adds to the losses carried', function () {
    $bank = Party::company('bank', Status::CreditInstitution);
    $outcome = $this->profit(new CloseYear($bank, 2026, Money::GEL(1_000), Money::GEL(1_600), lossesCarried: Money::GEL(100)));

    $this->assertNotTaxable($outcome, '121.4');
    expect($outcome->reason)->toContain('excess of 600.00 GEL')->toContain('Losses carried into 2027: 700.00 GEL');
});

test('current payments are credited against the years tax', function () {
    $bank = Party::company('bank', Status::CreditInstitution);
    $notes = fn (int $paid) => implode(' ', $this->profit(new CloseYear($bank, 2026, Money::GEL(1_000), Money::GEL(0), currentPayments: Money::GEL($paid)))->notes);

    expect($notes(150))->toContain('50.00 GEL left to pay by 2027-04-01') // 200 tax
        ->and($notes(260))->toContain('60.00 GEL overpaid');
});

test('a year before 2023 is left unresolved', function () {
    $year = fn (int $year) => $this->profit(new CloseYear(Party::company('bank', Status::CreditInstitution), $year, Money::GEL(1_000), Money::GEL(0)));

    expect($year(2022))->toBeInstanceOf(Unresolved::class)
        ->and($year(2022)->basis()->ref)->toBe('309.95');
    $this->assertTaxed($year(2023), '98.4', '20%', tax: 200); // 309.94
});

test('only banks and lenders are taxed on the year', function () {
    $this->assertNotTaxable($this->profit(new CloseYear($this->company, 2026, Money::GEL(1_000), Money::GEL(0))), '97.1');
    $this->assertNotTaxable($this->profit(new CloseYear(Party::foreignCompany('bank', 'DE'), 2026, Money::GEL(1_000), Money::GEL(0))), '96');
});

test('no other tax is charged on a closed year', function () {
    $report = $this->taxEngine()->process(new CloseYear(Party::company('bank', Status::CreditInstitution), 2026, Money::GEL(1_000), Money::GEL(0)));

    expect($report->outcomes->keys()->all())->toContain('vat', 'income', 'excise', 'import', 'property');
    foreach ($report->outcomes as $tax => $outcome) {
        if ($tax !== 'profit') {
            expect($outcome)->toBeInstanceOf(NotTaxable::class, "$tax: $outcome");
        }
    }
    $this->assertTaxed($report->for('profit'), '98.4', '20%', tax: 200);
});

test('a closed years figures cannot be negative', function () {
    new CloseYear(Party::company('bank', Status::CreditInstitution), 2026, Money::GEL(1_000), Money::GEL(-1));
})->throws(InvalidArgumentException::class);

/* ── Gambling organizers: the month (Art. 97.9–9³, 98.5–6, 309.16) ── */

test('a gaming hall pays twenty percent of the months stakes less winnings', function () {
    $hall = Party::company('hall', Status::GamblingOrganizer);
    $outcome = $this->profit(new CloseGamblingMonth($hall, 2026, 10, G::GamingHall, Money::GEL(100_000), Money::GEL(70_000)));

    $this->assertTaxed($outcome, '98.6', '20%', base: 30_000, tax: 6_000); // 97.9¹; not grossed up: 97.10 is for 97.1–3
    expect($outcome->collection)->toBe(Collection::MonthlyDeclaration)
        ->and($outcome->due->format('Y-m-d'))->toBe('2026-11-15') // 135.4, 153.10
        ->and($outcome->taxpayer)->toBe($hall)
        ->and($outcome->turnover())->toBeNull()
        ->and(implode(' ', $outcome->notes))->toContain('2026-10')->toContain('(97.9¹)');
});

test('online gambling is taxed like a gaming hall, and its foreign players part at five percent', function () {
    $casino = Party::company('casino', Status::GamblingOrganizer);

    $this->assertTaxed($this->profit(new CloseGamblingMonth($casino, 2026, 3, G::OnlineGambling, Money::GEL(50_000), Money::GEL(40_000))), '98.6', '20%', base: 10_000, tax: 2_000);
    $this->assertTaxed($this->profit(new CloseGamblingMonth($casino, 2026, 3, G::OnlineGambling, Money::GEL(50_000), Money::GEL(40_000), foreignPlayers: true)), '98.5', '5%', base: 10_000, tax: 500); // 97.9¹, 98.5
});

test('an online totalizator pays seven percent of the months stakes, winnings not deducted', function () {
    $outcome = $this->profit(new CloseGamblingMonth(Party::company('tote'), 2026, 12, G::OnlineTotalizator, Money::GEL(20_000), Money::GEL(18_000)));

    $this->assertTaxed($outcome, '309.16', '7%', base: 20_000, tax: 1_400); // 97.9
    expect($outcome->due->format('Y-m-d'))->toBe('2027-01-15'); // 309.16, 135.3
});

test('an online totalizators foreign players part is stakes less winnings at five percent', function () {
    $outcome = $this->profit(new CloseGamblingMonth(Party::company('tote'), 2026, 2, G::OnlineTotalizator, Money::GEL(20_000), Money::GEL(18_000), foreignPlayers: true));

    $this->assertTaxed($outcome, '98.5', '5%', base: 2_000, tax: 100);
    expect(implode(' ', $outcome->notes))->toContain('foreign citizens')->toContain('(97.9³)');
});

test('a month whose winnings exceed its stakes is not taxed', function () {
    $outcome = $this->profit(new CloseGamblingMonth(Party::company('hall'), 2026, 5, G::GamingHall, Money::GEL(10_000), Money::GEL(12_000)));

    $this->assertNotTaxable($outcome, '97.9¹');
    expect($outcome->reason)->toContain('Nothing to tax for 2026-05');
});

test('an individual organizer pays income tax, and a non-resident without a PE is unresolved', function () {
    $this->assertNotTaxable($this->profit(new CloseGamblingMonth(Party::person('giorgi'), 2026, 5, G::GamingHall, Money::GEL(10_000), Money::GEL(1_000))), '96');

    $foreign = $this->profit(new CloseGamblingMonth(Party::foreignCompany('bet', 'MT'), 2026, 5, G::OnlineGambling, Money::GEL(10_000), Money::GEL(1_000)));
    expect($foreign)->toBeInstanceOf(Unresolved::class)
        ->and($foreign->basis()->ref)->toBe('97.9¹');
});

test('no other tax is charged on a closed gambling month', function () {
    $report = $this->taxEngine()->process(new CloseGamblingMonth(Party::company('hall'), 2026, 10, G::GamingHall, Money::GEL(1_000), Money::GEL(500)));

    foreach ($report->outcomes as $tax => $outcome) {
        if ($tax !== 'profit') {
            expect($outcome)->toBeInstanceOf(NotTaxable::class, "$tax: $outcome");
        }
    }
    $this->assertTaxed($report->for('profit'), '98.6', '20%', tax: 100);
});

test('a gaming hall has no foreign players part', function () {
    new CloseGamblingMonth(Party::company('hall'), 2026, 10, G::GamingHall, Money::GEL(1_000), foreignPlayers: true);
})->throws(InvalidArgumentException::class);

test('a gambling months figures cannot be negative, nor its month out of range', function () {
    expect(fn () => new CloseGamblingMonth(Party::company('hall'), 2026, 10, G::GamingHall, Money::GEL(1_000), Money::GEL(-1)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new CloseGamblingMonth(Party::company('hall'), 2026, 13, G::GamingHall, Money::GEL(1_000)))->toThrow(InvalidArgumentException::class);
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
