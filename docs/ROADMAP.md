# Roadmap

The working list for the four packages: [money](https://github.com/MCHEKHASHVILI/money), [countries](https://github.com/MCHEKHASHVILI/countries), [tax-legislation](https://github.com/MCHEKHASHVILI/tax-legislation), [georgian-tax](https://github.com/MCHEKHASHVILI/georgian-tax). A daily session takes the top unblocked item, does it, and updates this file in the same push. The owner can reorder it, add items or answer questions here at any time.

## Questions for the owner

A question stays here until it is answered. Items that depend on it are blocked until then.

- 2026-11-03 — **A new law and its amendments together?** A draft can only cite acts and tax types already recorded, so today an amending law is approved first, then the amendments citing it are drafted and approved one by one. Options: (a) keep it so; (b) a batch: one submission holding the act and its amendments, which may cite the act still pending, approved or rejected as a whole. **Recommendation: (a) for the MVP**, (b) later if entering laws one change at a time proves slow. Blocks nothing; the JSON API is built for (a) and can take batches later.

### Answered

- 2026-11-02 — **Drafts and approval:** an amendment needs approval before it takes effect.
- 2026-11-02 — **Forms UI:** for now, a JSON API. A frontend comes later.
- 2026-11-02 — **Who may edit what:** to be discussed later. Until then, authorization stays with the app's policies, and approval goes through a gate the app defines.
- 2026-11-02 — **Past Georgian law:** a future agent will enter it. Not in this list.
- 2026-11-02 — **Release (v1.0.0 tags, Packagist):** once the MVP below is done. Tell the owner when it is, so he can tag.

## MVP

The MVP is done when the items marked **[MVP]** in "Next" are done. This definition is proposed, and the owner can change it.

## Next

In order. Skip anything blocked by an open question.

1. **[MVP] The remaining hard-coded law → thresholds and tables:**
   - ImportRelief limits (GEL caps per relief, 199.დ). While at it, check the excise side: `Excise\Stage\ApplyExemptions` applies the same caps to the 194.5 reliefs, but 194.5.ბ¹ (post) sets only quantities, and 194.5.თ covers cars imported under 199.დ.დ/დ.ე, while 199.დ.დ's 15,000 GEL excludes chapter 87;
   - excise rule constants: cars and motorcycles per cm³ by age, tobacco, alcohol, calls (188.1, 188.3);
   - municipal property rate caps and base rates (204, 202).

   Each becomes a key in `resources/law/*.php` and `GeorgiaLegislationSeeder`, with a test that amending it changes the result.
2. **[MVP] Store the country on assessments.** Add a `country` column to `tax_assessments`.
3. **[MVP] Refresh READMEs and `CATALOGUE.md`** against the code. When this is done, report to the owner that the MVP is ready to tag.
4. **Features the README lists as not modelled**, one per session, each with tests citing the article:
   - 197.4–5: temporary admission at 3% a month;
   - 189: excise credit;
   - 189.8 and 198.3: refunds;
   - 98.4: banks;
   - 177: VAT apportionment;
   - 205.3: property tax instalments.
5. **Per-country ledger.** Found while doing the per-country tables: `Ledger` (and georgian-tax's `YearToDate`) is still one global binding, so a second country's package would record into, and read year-to-date totals from, Georgia's ledger. Key it by country the way `CountryTables` does.
6. **API: a draft's diff.** Found while doing the JSON API: `GET drafts/{id}` shows the payload, but not the value in force it would close. A reviewer approving an amendment wants both side by side (`current`: the row in force on the draft's `from`). Small; after the MVP.
7. **Gaps found in 82.2 and 98⁴** (2026-11-05), each small, with tests citing the article:
   - 82.2.ა.ვ is not modelled: a high-mountain resident with three or more children under 18 pays no income tax on salary from a budget organization there; with one or two, the tax on the first 3,000 GEL of it a year is halved.
   - 98⁴.2: when last year's costs exceeded revenue, the representation limit is 1% of the costs. `RepresentationOverLimit` uses revenue only (its docblock says otherwise); `CompanyBooks` needs last year's costs.
8. **Check the Code for amendments.** Compare the cited articles with the latest consolidated text on matsne.gov.ge, and record each change through the actions (in both the files and the seeder), citing the amending law.

## Done

Newest first. One line per session: the date, what changed, and the commits.

- 2026-11-05: **[MVP] Hard-coded law → thresholds and rates, part 1: personal allowances and profit tax limits.** The 82.2 annual allowances are income thresholds `allowance` (82.2.ა, 3,000), `allowance_disability` (82.2.ბ, 6,000), `allowance_peacekeeper` (82.2.ბ¹, 6,000), `allowance_high_mountain` (82.2.გ, 6,000); `Relief::annualAllowance()` is replaced by `Relief::thresholdKey()`, and `ApplyAnnualAllowance` picks the highest allowance in force on the day (82.3). The profit tax limits are rates `98³.3.ა` (charity, 10%), `98⁴.2` and `98⁴.3` (representation, 1%), read through a new `Outflow::rate()`. Both in `resources/law/*.php` and the seeder (17 thresholds). Tests: the highest of two allowances; amending `allowance` and `98⁴.2` through `AmendLaw` changes the result from the amendment's date. Tested on Laravel 13 and 12, file and database. georgian-tax (this commit).
- 2026-11-04: **[MVP] JSON API.** tax-legislation: `Http\Routes` registers optional routes (config `tax-legislation.api`: `enabled` off by default, `prefix` `api/tax-legislation`, `name`, `middleware` `['api']`), or an app calls `Routes::register()` in its own group. Read: countries with law recorded (counts; `?all=1`), a country's acts (paginated, newest first) and taxes (all periods, or `?on=` in force), an act with what it did, a tax's law on a day (`tax-types/{id}/law?on=`), one value's history (`history/{kind}/{key}`), one value. Write: `POST legal-acts`, `tax-types`, `tax-types/{id}/abolish`, `amendments`, `values/{kind}/{id}/repeal` — 202 with the draft while approval is on, 201 with the record when off. Drafts: list (pending by default, `?status=`, `?country=`), show, approve, reject. Revisions: newest first, by `subject`/`subject_id` or `draft_id`. API Resources throughout (records point to each other as `{type, id}`, money as `{amount, currency}`); every answer is JSON (an `ExpectsJson` middleware), validation and decided drafts 422, gate refusal 403. Adds `illuminate/http` and `illuminate/routing` to `require`. 12 Testbench HTTP tests; georgian-tax: a test that reads Georgia's VAT law and amends it through the API, draft to approval. Tested on Laravel 13 and 12, file and database. tax-legislation f2297c5, georgian-tax (this commit).
- 2026-11-03: **[MVP] Drafts and approval.** tax-legislation: with `approval.required` on (default), every action saves a pending `Drafts\Draft` (own table `legislation_drafts`, new migration) and returns it; the legislation tables never hold it, so the engine, `inForceOn` and `Law` ignore it. `ApproveDraft` locks the draft, re-runs the action's validation and checks (period, overlaps, act's country) against the law at that moment and applies it in one transaction; `RejectDraft` keeps it with a required reason. Drafter, decider, `decided_at`, note and result are on the draft; applied changes' revisions name the approver and link the draft (`legislation_revisions.draft_id`). Who may decide: the gate `approval.gate` (`approve-legislation`), which the app defines; null skips the check. `DraftSubmitted`/`DraftDecided` events. Approval off, or `$action->directly()`, applies at once (tests, seeders, imports). georgian-tax: a test that a drafted VAT change does nothing until approved. tax-legislation 6c43eb4, georgian-tax (this commit).

- 2026-11-02: **[MVP] Per-country bindings.** tax-legislation's `Rates\CountryTables` keeps rates, thresholds and tables per country (scoped, once per request; an unregistered country reads its recorded history). `Tax::rateTable()`, `thresholdTable()`, `tableSource()` read the tax's own country, and a type-hinted `RateTable`/`ThresholdTable`/`TableSource` in a stage resolves to the assessing tax's country. georgian-tax registers `GE` instead of binding global singletons. Tested with invented Armenian and Azerbaijani taxes sharing article keys, and a second country beside Georgia. tax-legislation 9d5f500, georgian-tax (this commit).
- 2026-11-02: Added the Tax Code text (`docs/law`), the sandbox setup (`tools/sandbox`) and this roadmap. Daily sessions start.
- 2026-11-02: The owner answered the five questions: approval required, JSON API, roles deferred, past law left to a future agent, tag once the MVP is done.
