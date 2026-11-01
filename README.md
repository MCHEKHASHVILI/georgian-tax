# georgian-tax

The Tax Code of Georgia as a Laravel package. All six taxes of Art. 6 — income tax, profit tax, VAT, excise, import tax and the local property tax — run as one pipeline of taxes, each tax a pipeline of its own stages and rules.

It is Georgia's law on top of [`mchekhashvili/tax-legislation`](https://github.com/MCHEKHASHVILI/tax-legislation), which provides the country-neutral engine and keeps the law's history: legal acts, tax types, rates, thresholds, exemptions and tables, per country, with the dates they were in force.

Every rule, exemption and source rule carries an `#[Article('134.1.ე')]` attribute, so each answer names the line of the law behind it. `docs/CATALOGUE.md` lists every event the Code defines for each tax.

Requires PHP 8.4 and Laravel 12 or 13. Money is [`mchekhashvili/money`](https://github.com/MCHEKHASHVILI/money): integer tetri, exact arithmetic. Countries are [`mchekhashvili/countries`](https://github.com/MCHEKHASHVILI/countries).

## Install

Until both packages are on Packagist, add their repositories to your app's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/MCHEKHASHVILI/georgian-tax" },
    { "type": "vcs", "url": "https://github.com/MCHEKHASHVILI/tax-legislation" },
    { "type": "vcs", "url": "https://github.com/MCHEKHASHVILI/countries" },
    { "type": "vcs", "url": "https://github.com/MCHEKHASHVILI/money" }
]
```

```
composer require mchekhashvili/money:^1.0@dev mchekhashvili/countries:^1.0@dev mchekhashvili/tax-legislation:^1.0@dev mchekhashvili/georgian-tax:dev-main
php artisan migrate
php artisan db:seed --class="GeorgianTax\Database\Seeders\GeorgiaLegislationSeeder"   # Georgia's law into the history tables
php artisan vendor:publish --tag=georgian-tax-config     # optional
```

The service provider and the `GeorgianTax` facade register themselves. (The `@dev` and `dev-main` go away once the repositories have `v1.0.0` releases.)

## Usage

```php
use GeorgianTax\Facades\GeorgianTax;

$report = GeorgianTax::process(new Pay(
    payer:  Party::company('acme'),
    payee:  Party::foreignCompany('gmbh', 'DE'),
    for:    IncomeKind::ServiceFee,
    amount: Money::GEL(1000),
    on:     new DateTimeImmutable('2026-10-30'),
    place:  Place::abroad('DE'),
));

echo $report->for('income');
// Art. 134.1.ე — Other Georgian-source income of a non-resident: 10% of 1000.00 GEL = 100.00 GEL,
// withheld by acme, due 2026-11-15

echo $report->for('vat');
// Art. 161.1.ა — Reverse charge: 18% of 1000.00 GEL = 180.00 GEL, reverse-charged by acme, due 2026-11-15

$report->netToRecipient();   // 900.00 GEL — VAT is on top, not withheld
$report->outcomes;           // Collection keyed by tax: import, excise, vat, income, profit, property
```

| Call | Does |
|---|---|
| `GeorgianTax::process($event)` | Assesses, records in the year-to-date ledger, stores the report, fires events |
| `GeorgianTax::assess($event)` | A quote: assesses only — nothing recorded, nothing fired |
| `GeorgianTax::queue($event)` | Processes on the queue (`AssessTaxableEvent`) |
| `GeorgianTax::only('vat', 'income')` | The same engine with some taxes |
| `GeorgianTax::ledger()` | Year-to-date totals: `received()`, `paid()`, `turnover()`, `spent()` |

Or type-hint `GeorgianTax\Engine\TaxEngine` anywhere the container resolves.

An imported car goes through three taxes at once:

```php
$report = GeorgianTax::process(new Import(
    importer:     Party::company('autos', Status::VatPayer),
    customsValue: Money::GEL(20_000),
    on:           new DateTimeImmutable('2026-10-30'),
    commodity:    new Commodity('8703 23', engineCc: 2000, yearMade: 2019),
));

echo $report->for('import');  // Art. 197.6 — Passenger car import tax: 2000 cm³ × 0.05 GEL = 135.00 GEL
                              //   (100 GEL for the engine, plus 5% of it for each of 7 years in use)
echo $report->for('excise');  // Art. 188.13 — Passenger car: 2000 cm³ × 4.50 GEL = 9000.00 GEL
echo $report->for('vat');     // Art. 165².2.ა — VAT on import: 18% of 29135.00 GEL = 5244.30 GEL
                              //   (customs value plus import tax and excise)
```

Property tax looks at holdings, one per item per year:

```php
GeorgianTax::process(new OwnProperty(Party::company('acme'), new PropertyItem(PropertyKind::Building, Money::GEL(100_000), acquiredYear: 1998), 2026));
// Art. 202.1 — Company property: 1% of 300000.00 GEL = 3000.00 GEL, due 2027-04-01
// (bought before 2000, so the book value is revalued ×3)
```

## In your Laravel app

**Database.** georgian-tax's migration creates the ledger tables, prefixed `georgian_tax_`; reports and the law's history live in tax-legislation's tables:

| Table | Holds |
|---|---|
| `income_entries` | Income per recipient and payer — the gift, heir, small-business and allowance thresholds read it |
| `turnover_entries` | Turnover per party and tax — VAT's rolling 12 months |
| `spending_entries` | Company spending by category — charity and representation limits |
| `company_years` | Revenue, net profit and founding year — fill it from your accounting with `EloquentBooks::close()`, or bind your own `CompanyBooks` |
| (tax-legislation) `tax_assessments`, `tax_assessment_outcomes` | Every report: each tax's outcome, article, base, rate, amount, who pays and when |

Ledger models live in `GeorgianTax\Ledger\Eloquent\Models`, reports in `Mchekhashvili\TaxLegislation\Assessments\Models` — `Assessment::with('outcomes')`, `AssessedOutcome::where('due_on', '<=', now())` — and money columns cast to `Money`. `Assessment::event()` gives back the original event, to re-assess after a rate change.

**Events.** From tax-legislation, plus Georgia's own:

| Event | When |
|---|---|
| `TaxableEventAssessed` (tax-legislation) | After every `process()` — carries the `TaxReport` |
| `VatRegistrationRequired` | An unregistered supplier's 12-month turnover passed 100,000 GEL (165.1); `deadline()` is 2 working days on |

```php
Event::listen(VatRegistrationRequired::class, fn ($e) => Notification::send($accountants, new RegisterForVat($e->supplier, $e->deadline())));
```

**Queue.** `GeorgianTax::queue($event)` dispatches tax-legislation's `AssessTaxableEvent` on `config('tax-legislation.queue')`. Each event reads the totals earlier ones left, so keep one party's events in order — one worker, or a chain.

**Config** (`config/georgian-tax.php`):

| Key | Default | |
|---|---|---|
| `taxes` | all six, import tax first | Order matters: VAT reads import tax and excise. Add your own `Tax` subclass here. |
| `pipelines.<tax>` | — | Replace a tax's stages and rules without forking: same shape as `Tax::stages()` |
| `ledger` | `eloquent` | `memory` for per-request quotes and tests |
| `books` | `EloquentBooks` | Your `CompanyBooks` implementation |
| `law.source` | `file` | `database` reads rates, with their history, from tax-legislation's tables |
| `database.*` | default connection, `georgian_tax_` | Connection, table prefix, whether to run the migrations |
| `property.*` | the Code's caps | Your municipal councils' property and land rates, checked against the caps |
| `law.*` | package tables | Point at your own copy of a law table to follow an amendment early (`vendor:publish --tag=georgian-tax-law`) |

**Container.** Stages, rules and taxes are built by the container, so a rule can type-hint any service. `RateTable`, `ExciseTable`, `ImportTable`, `LandRates`, `MunicipalRates`, `YearToDate` and `CompanyBooks` are bindings you can replace. The engine, outcomes, `#[Article]` and `Citation` come from tax-legislation.

## Legislation history

Georgia's law lives in `mchekhashvili/tax-legislation`'s tables, per country, with the dates it was in force and the acts behind it. `GeorgiaLegislationSeeder` loads what the Code says today:

| | |
|---|---|
| Legal acts | The Tax Code №3591-IIს of 17 September 2010, in force from 1 January 2011 (Art. 310.1); the 2004 Tax Code it repealed; Law №6942 of 15 July 2020, the latest amendment of Art. 6 |
| Tax types | The six taxes of Art. 6.5–6.6, with their engine classes |
| Rates | Every rate in `resources/law/rates.php`, plus the 204.2 and 204.3 land rates |
| Tables | The excise table (188.1), import tax lists (197.1–197.3, 199.პ), land rates by municipality (204.1) |
| Thresholds | VAT registration 100,000 GEL (165.1), small business 500,000 GEL (90.2), gifts 1,000 GEL (82.1.თ), distant heirs 150,000 GEL (82.1.ი), family income 100,000 GEL (206.1.ა) |

Start dates are left open, meaning in force since before the recorded history. Exemptions aren't seeded yet, because the engine still applies them in code. Past taxes (the social tax and others) and earlier rates are added with `amend()`, `abolish()` and new `TaxType` rows.

With `GEORGIAN_TAX_LAW_SOURCE=database`, the engine reads rates from that history instead of the file. A rate change recorded with `TaxRate::amend($vat, '166', ['percent' => 20], from: '2027-01-01', by: $law)` then applies to events from that day on, with no code change.

## Structure

```
src/
  GeorgianTaxServiceProvider.php   registers Georgia's taxes, ledger, tables, report macros
  Facades/GeorgianTax.php
  Domain/
    Party/        Party, PartyKind, Residency, Regime, Status, Relief, ResidencyResolver (Art. 34)
    Asset/        Asset, AssetType — what an individual sells
    Income/       IncomeKind, SupplyCategory (VAT), HeirOrder, Place
    Goods/        Commodity (HS code and measurements), ImportRelief
    Property/     PropertyItem, PropertyKind, PropertyExemption
    Event/        the verbs: Pay, Sell, Gift, Inherit, Import, SelfSupply, Refund, Expense,
                  NonMarketDeal, OwnProperty
  Engine/
    Tax.php       a Georgian tax: country GE, lari, config('georgian-tax.pipelines')
    Deadline.php  when each way of collecting is due under Georgian law
  IncomeTax/      Assessment → IdentifyTaxpayer → ResolveSource → ApplyExemptions
                  → ApplySpecialRegime → ApplyAnnualAllowance → ApplyRules
  Vat/            Supply → IdentifyOperation → ResolvePlaceOfSupply → ApplyExemptions → ApplyLiability
  ProfitTax/      Outflow → IdentifyOutflow → ApplyExemptions → ApplyRules
  Excise/         Duty → IdentifyOperation → ApplyExemptions → ApplyRate
  ImportTax/      Clearance → IdentifyImport → ApplyExemptions → ApplyRules
  PropertyTax/    Holding → IdentifyTaxpayer → ApplyExemptions → ApplyRules
  Database/       the base model: table prefix and connection from config
  Ledger/         YearToDate (tax-legislation's Ledger), CompanyBooks; InMemoryLedger;
                  Eloquent/ ledger, books and models
  Events/         VatRegistrationRequired
  Listeners/      FlagVatRegistration
config/georgian-tax.php
database/migrations/ database/seeders/ — GeorgiaLegislationSeeder
resources/law/    rates.php, excise.php, import.php, property.php — the Code's tables
```

## The two pipelines

**Outer — taxes.** `TaxEngine` passes a `TaxReport` through each `Tax`. Each tax adds its outcome and passes it on, so a later tax can read an earlier one: VAT adds import tax and excise to its import base. Both levels run on Laravel's `Illuminate\Pipeline\Pipeline`; every stage has a `handle($subject, $next)` method, like HTTP middleware.

Each tax reads the same event in its own terms. A `Pay` of 1,000 GEL for a service is income to the payee for income tax, a supply by the payee for VAT, and an ordinary cost for profit tax. A `Pay` of a producer's spirits also carries excise.

**Inner — income tax stages.** Each stage either answers or calls `$next`:

| Stage | Article | Answers when |
|---|---|---|
| `IdentifyTaxpayer` | 79 | The recipient is the state, a resident company (profit tax), or a PE; or there is no income |
| `ResolveSource` | 104 | The income is not Georgian-source |
| `ApplyExemptions` | 82, 103, … | An exemption applies |
| `ApplySpecialRegime` | 83–95⁶ | Micro, small business or fixed-tax business income |
| `ApplyAnnualAllowance` | 82.2 | Never answers alone — reduces the base the rules return |
| `ApplyRules` | 80–81, 130–134, 154 | Always — the list ends with catch-alls |

**Inner — VAT stages:**

| Stage | Article | Answers when |
|---|---|---|
| `IdentifyOperation` | 158–160³ | It isn't a supply, import or deemed supply — salary, a private one-off sale, a gift without credited input VAT |
| `ResolvePlaceOfSupply` | 162–162¹ | The supply is made outside Georgia |
| `ApplyExemptions` | 170–173 | Exempt without credit (`Exempt`), with credit (`ExemptWithCredit`), or an exempt import |
| `ApplyLiability` | 161, 165, 168 | Always — import, reverse charge, foreign supplier, registered supplier, first-supply obligation, or the 100,000 GEL threshold |

An unregistered supplier's supplies below the threshold come back `NotTaxable` but still count toward it. The ledger keeps a rolling 12 months.

**Inner — profit, excise, import and property tax.** Same shape: identify, exempt, then a first-match list of rate rules.

| Tax | Identify | Exemptions | Rates |
|---|---|---|---|
| Profit | 96–97: a resident company paying out — dividend, non-market deal, non-business cost, free supply | 98¹.2, 98³.3, 99, 23¹ | 98⁴ representation over 1%, 23.10 international 5%, 98.1 15% grossed up |
| Excise | 182–187: producer's supply, import, export, gas for vehicles, call termination | 194.5–7 | 188.9–18 cars, 188.1 motorcycles and table, 188.1¹ tobacco, 188¹ alcohol, 188.3 calls |
| Import | 195–196: an import with an HS code | 199 by code and by relief | 197.6 cars, 197.3 alcohol in euro, 197.1–2 lists, else 0% |
| Property | 201, 203: an `OwnProperty` holding | 206, with the 206.2 leasing carve-out | 204 land, 202.1–3¹ company, 202.5 individual |

Excise and import tax per unit come back as `Levied` (quantity × rate per unit, plus any ad-valorem part).

## Adding a rule

```php
#[Article('154.1.x', 'What it covers')]
final class MyRule implements Rule
{
    public function __construct(private SomeService $service) {}   // resolved by the container

    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->is(IncomeKind::Something)) {
            return null;
        }

        return $a->withheld($this, '81.1');   // or ->declared(...), ->unresolved(...)
    }
}
```

List it in `IncomeTax::stages()` under `ApplyRules`, before any more general rule — or, in an app, in `config('georgian-tax.pipelines.income')`. `withheld()` switches to the recipient's declaration on its own when the payer isn't a tax agent (Art. 154.1), so rules never repeat that check.

## Describing a VAT supply

For VAT, `Pay::$amount` is the price without VAT; pass `pricesIncludeVat: true` if it includes it. When an exemption or place-of-supply rule turns on what is supplied, say so:

```php
new Pay($patient, $clinic, IncomeKind::ServiceFee, Money::GEL(200), $today, supply: SupplyCategory::MedicalCare);
new Pay($foreignBuyer, $exporter, IncomeKind::GoodsSupply, Money::GEL(50_000), $today, Place::exportTo('AM'));
new Import($importer, customsValue: Money::GEL(10_000), on: $today, dutiesAndFees: Money::GEL(1000));
new SelfSupply($company, Money::GEL(10_000), $today, SelfSupplyReason::Deregistration);
```

Interest and insurance are treated as financial services, land and securities sales as exempt, and international transport as export transport, without any category given.

## What the engine does not decide

It returns `Unresolved`, with the citation, where the Code hands the rule to another act:

- free-industrial-zone salaries (Government resolution, 153.9);
- international organizations with diplomatic status (Minister's order, 153.8).

Outside the per-event model:

- fixed-tax amounts per object (95³.1.ა);
- gaming organizers' monthly net (80.7);
- the annual recalculation with deductions (Chapter XV);
- VAT on forced sales by enforcement or insolvency administrators (161¹), and collateral taken over by a creditor (161.1.ბ);
- VAT credit apportionment for mixed use (177) and the monthly netting of output against input VAT (`creditableBy` says who may credit, not how much);
- tax-free refunds to foreign shoppers (181.5);
- banks' profit tax on annual net profit (98.4);
- excise credit for excise raw materials (189) and refunds on re-exported cars (189.8, 198.3);
- import tax on temporary admission at 3% a month (197.4);
- the 15 June current payment of company property tax (205.3) — the same amount, paid early.

Where an amount needs something only the caller knows — a retail price for cigarettes, the euro rate for alcohol, last year's family income for property tax — the engine returns `Unresolved` or `NotTaxable` naming it.

## Rates

`resources/law/rates.php` maps each article to `[effective-from => percent]`. The percents match the 30/09/2026 consolidation. The dates are not historic, so check the rate history before assessing pre-2026 events.

`resources/law/excise.php` (188.1 table), `import.php` (197 lists and 199.პ) and `property.php` (204 land rates by municipality) hold the tables. Override any of them through `config('georgian-tax.law')`.

## Tests

```
composer install
composer test
```

Pest on Orchestra Testbench. `tests/Taxes` has one file per tax with one test per catalogued case, plus checks that every rule cites an article; `tests/Laravel` covers the provider, config, facade, Eloquent ledger, stored reports, events, queue, and the Georgia legislation seeder with the engine on database rates. CI runs both on Laravel 12 and 13.
