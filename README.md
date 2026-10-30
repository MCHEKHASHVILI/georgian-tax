# georgian-tax

Applies the Tax Code of Georgia to business events. Income tax is implemented in full; profit tax, VAT and the rest plug into the same engine.

Every rule, exemption and source rule carries an `#[Article('134.1.ე')]` attribute, so each answer the engine gives names the line of the law behind it. `docs/CATALOGUE.md` lists every income-tax event the Code defines.

## Usage

```php
$ledger = new InMemoryLedger();                       // back with your database in production
$engine = new TaxEngine($ledger, IncomeTax::standard($ledger, ConfigRateTable::standard()));

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

$report->netToRecipient();   // 900.00 GEL
```

## Structure

```
src/
  Law/            #[Article] attribute and Citation — the link from code to the Code
  Domain/
    Party/        Party, PartyKind, Residency, Regime, Status, Relief, ResidencyResolver (Art. 34)
    Asset/        Asset, AssetType — what an individual sells
    Income/       IncomeKind, HeirOrder, Place
    Event/        the verbs: Pay, Sell, Gift, Inherit, Refund
  Engine/
    Pipeline/     generic middleware pipeline, used at both levels
    Outcome/      Taxable, Exempt, NotTaxable, Unresolved, Collection
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
  Rates/          rates keyed by article and effective date — config/rates.php
  Ledger/         year-to-date totals for the annual thresholds
```

## The two pipelines

**Outer — taxes.** `TaxEngine` passes a `TaxReport` through each `Tax`. Each tax adds its outcome and passes it on. To add VAT, extend `Tax`, implement `assess()`, and register it: `new TaxEngine($ledger, $incomeTax, $vat)`.

**Inner — income tax stages.** Each stage either answers or calls `$next`:

| Stage | Article | Answers when |
|---|---|---|
| `IdentifyTaxpayer` | 79 | The recipient is the state, a resident company (profit tax), or a PE; or there is no income |
| `ResolveSource` | 104 | The income is not Georgian-source |
| `ApplyExemptions` | 82, 103, … | An exemption applies |
| `ApplySpecialRegime` | 83–95⁶ | Micro, small business or fixed-tax business income |
| `ApplyAnnualAllowance` | 82.2 | Never answers alone — reduces the base the rules return |
| `ApplyRules` | 80–81, 130–134, 154 | Always — the list ends with catch-alls |

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

## What the engine does not decide

It returns `Unresolved`, with the citation, where the Code hands the rule to another act:

- free-industrial-zone salaries (Government resolution, 153.9);
- international organizations with diplomatic status (Minister's order, 153.8).

Outside the per-event model:

- fixed-tax amounts per object (95³.1.ა);
- gaming organizers' monthly net (80.7);
- the annual recalculation with deductions (Chapter XV).

## Rates

`config/rates.php` maps each article to `[effective-from => percent]`. The percents match the 30/09/2026 consolidation. The dates are not historic, so check the rate history before assessing pre-2026 events.

## Tests

```
composer install
composer test
```

`tests/IncomeTaxTest.php` has one test per catalogued case, plus a check that every rule cites an article.
