# georgian-tax

Applies the Tax Code of Georgia to business events. All six taxes of Art. 6 are implemented — income tax, profit tax, VAT, excise, import tax and the local property tax — each as one stage of the same engine.

Every rule, exemption and source rule carries an `#[Article('134.1.ე')]` attribute, so each answer the engine gives names the line of the law behind it. `docs/CATALOGUE.md` lists every event the Code defines for each tax.

Requires PHP 8.4.

## Usage

```php
$ledger = new InMemoryLedger();                       // back with your database in production
$rates  = ConfigRateTable::standard();
$books  = new StaticBooks();                          // last year's revenue and profit, for profit tax limits
$engine = new TaxEngine(
    $ledger,
    ImportTax::standard(),        // first: import tax and excise join the VAT base on import
    Excise::standard(),
    Vat::standard($ledger, $rates),
    IncomeTax::standard($ledger, $rates),
    ProfitTax::standard($ledger, $rates, $books),
    PropertyTax::standard(),      // or ::standard(new MunicipalRates(...)) with the council's rates
);

$report = $engine->process(new Pay(
    payer:  Party::company('acme'),
    payee:  Party::foreignCompany('gmbh', 'DE'),
    for:    IncomeKind::ServiceFee,
    amount: Money::gel(1000),
    on:     new DateTimeImmutable('2026-10-30'),
    place:  Place::abroad('DE'),
));

echo $report->for('income');
// Art. 134.1.ე — Other Georgian-source income of a non-resident: 10% of 1000.00 GEL = 100.00 GEL,
// withheld by acme, due 2026-11-15

echo $report->for('vat');
// Art. 161.1.ა — Reverse charge: 18% of 1000.00 GEL = 180.00 GEL, reverse-charged by acme, due 2026-11-15

$report->netToRecipient();   // 900.00 GEL — VAT is on top, not withheld
```

An imported car goes through three taxes at once:

```php
$report = $engine->process(new Import(
    importer:     Party::company('autos', Status::VatPayer),
    customsValue: Money::gel(20_000),
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
$engine->process(new OwnProperty(Party::company('acme'), new PropertyItem(PropertyKind::Building, Money::gel(100_000), acquiredYear: 1998), 2026));
// Art. 202.1 — Company property: 1% of 300000.00 GEL = 3000.00 GEL, due 2027-04-01
// (bought before 2000, so the book value is revalued ×3)
```

## Structure

```
src/
  Law/            #[Article] attribute and Citation — the link from code to the Code
  Domain/
    Party/        Party, PartyKind, Residency, Regime, Status, Relief, ResidencyResolver (Art. 34)
    Asset/        Asset, AssetType — what an individual sells
    Income/       IncomeKind, SupplyCategory (VAT), HeirOrder, Place
    Goods/        Commodity (HS code and measurements), ImportRelief
    Property/     PropertyItem, PropertyKind, PropertyExemption
    Event/        the verbs: Pay, Sell, Gift, Inherit, Import, SelfSupply, Refund, Expense,
                  NonMarketDeal, OwnProperty
  Engine/
    Pipeline/     generic middleware pipeline, used at both levels
    Outcome/      Taxable (a percentage), Levied (per unit), Exempt, ExemptWithCredit,
                  NotTaxable, Unresolved, Collection
    Tax.php       one tax = one stage of the engine pipeline
    TaxEngine.php runs an event through every tax
    TaxReport.php what every tax said about the event
  IncomeTax/
    IncomeTax.php wires the income tax pipeline
    Assessment.php the subject flowing through it, with the outcome builders
    Stage/        IdentifyTaxpayer → ResolveSource → ApplyExemptions → ApplySpecialRegime
                  → ApplyAnnualAllowance → ApplyRules
    Source/       Art. 104, one class per paragraph
    Exemption/    Art. 82 and the exemptions in 23, 94, 103, 130–132
    Rule/         NonResident/ (134), Withholding/ (130–133³, 154), Declared/ (80, 81, 153)
  Vat/
    Vat.php       wires the VAT pipeline
    Supply.php    the subject flowing through it — supplier, customer, base, outcome builders
    Stage/        IdentifyOperation → ResolvePlaceOfSupply → ApplyExemptions → ApplyLiability
    Place/        Art. 162–162¹, one class per rule
    Exemption/    Art. 170–173 — category tables plus export and party-based exemptions
    Rule/         import, reverse charge, foreign supplier, registered, first-supply, threshold
  ProfitTax/      Outflow → IdentifyOutflow → exemptions (98¹.2, 98³.3, 99) → rules (98⁴, 23, 98)
  Excise/         Duty → IdentifyOperation → ApplyExemptions → Rate/ (cars, motorcycles,
                  tobacco, alcohol, calls, the 188.1 table in config/excise.php)
  ImportTax/      Clearance → IdentifyImport → ApplyExemptions → Rule/ (cars, alcohol in euro,
                  the 12% and 5% lists in config/import.php)
  PropertyTax/    Holding → IdentifyTaxpayer → ApplyExemptions → Rule/ (land, company,
                  individual); MunicipalRates holds the council's decisions within the caps
  Rates/          rates keyed by article and effective date — config/rates.php
  Ledger/         year-to-date totals for the annual thresholds
```

## The two pipelines

**Outer — taxes.** `TaxEngine` passes a `TaxReport` through each `Tax`. Each tax adds its outcome and passes it on, so a later tax can read an earlier one: VAT adds import tax and excise to its import base. To add a tax, extend `Tax`, implement `assess()` (or `assessIn()` to see the report), and register it.

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
    public function assess(Assessment $a): ?Outcome
    {
        if (!$a->is(IncomeKind::Something)) {
            return null;
        }

        return $a->withheld($this, '81.1');   // or ->declared(...), ->unresolved(...)
    }
}
```

Register it in `IncomeTax::standard()` before any more general rule. `withheld()` switches to the recipient's declaration on its own when the payer isn't a tax agent (Art. 154.1), so rules never repeat that check.

## Describing a VAT supply

For VAT, `Pay::$amount` is the price without VAT; pass `pricesIncludeVat: true` if it includes it. When an exemption or place-of-supply rule turns on what is supplied, say so:

```php
new Pay($patient, $clinic, IncomeKind::ServiceFee, Money::gel(200), $today, supply: SupplyCategory::MedicalCare);
new Pay($foreignBuyer, $exporter, IncomeKind::GoodsSupply, Money::gel(50_000), $today, Place::exportTo('AM'));
new Import($importer, customsValue: Money::gel(10_000), on: $today, dutiesAndFees: Money::gel(1000));
new SelfSupply($company, Money::gel(10_000), $today, SelfSupplyReason::Deregistration);
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

`config/rates.php` maps each article to `[effective-from => percent]`. The percents match the 30/09/2026 consolidation. The dates are not historic, so check the rate history before assessing pre-2026 events.

`config/excise.php` (188.1 table), `config/import.php` (197 lists and 199.პ) and `config/property.php` (204 land rates by municipality) hold the tables.

## Tests

```
composer install
composer test
```

One test file per tax — `IncomeTaxTest`, `VatTest`, `ProfitTaxTest`, `ExciseTest`, `ImportTaxTest`, `PropertyTaxTest` — with one test per catalogued case, plus checks that every rule cites an article.
