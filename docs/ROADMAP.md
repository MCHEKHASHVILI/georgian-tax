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

1. **[MVP] JSON API (tax-legislation).** Optional routes, behind a config switch with a prefix and middleware:
   - read: countries, legal acts, tax types, and values in force on a date, with their history;
   - write: the actions, creating drafts (`handle()` returns a `Draft` while approval is on);
   - drafts: list (pending, by country), show, approve (`ApproveDraft`), reject (`RejectDraft`, reason required) — an `AuthorizationException` from the gate becomes 403, a decided draft 422;
   - the revision log.

   Use API Resources, return validation errors as 422, and leave authentication to the app's middleware. Test it with Testbench HTTP tests.
2. **[MVP] The remaining hard-coded law → thresholds and tables:**
   - personal income tax relief allowances (82.2);
   - ImportRelief limits (GEL caps per relief);
   - excise rule constants: cars and motorcycles per cm³ by age, tobacco, alcohol, calls (188.1, 188.3);
   - profit tax limits: charity 10% and representation 1%;
   - municipal property rate caps and base rates (204, 202).

   Each becomes a key in `resources/law/*.php` and `GeorgiaLegislationSeeder`, with a test that amending it changes the result.
3. **[MVP] Store the country on assessments.** Add a `country` column to `tax_assessments`.
4. **[MVP] Refresh READMEs and `CATALOGUE.md`** against the code. When this is done, report to the owner that the MVP is ready to tag.
5. **Features the README lists as not modelled**, one per session, each with tests citing the article:
   - 197.4–5: temporary admission at 3% a month;
   - 189: excise credit;
   - 189.8 and 198.3: refunds;
   - 98.4: banks;
   - 177: VAT apportionment;
   - 205.3: property tax instalments.
6. **Per-country ledger.** Found while doing the per-country tables: `Ledger` (and georgian-tax's `YearToDate`) is still one global binding, so a second country's package would record into, and read year-to-date totals from, Georgia's ledger. Key it by country the way `CountryTables` does.
7. **Check the Code for amendments.** Compare the cited articles with the latest consolidated text on matsne.gov.ge, and record each change through the actions (in both the files and the seeder), citing the amending law.

## Done

Newest first. One line per session: the date, what changed, and the commits.

- 2026-11-03: **[MVP] Drafts and approval.** tax-legislation: with `approval.required` on (default), every action saves a pending `Drafts\Draft` (own table `legislation_drafts`, new migration) and returns it; the legislation tables never hold it, so the engine, `inForceOn` and `Law` ignore it. `ApproveDraft` locks the draft, re-runs the action's validation and checks (period, overlaps, act's country) against the law at that moment and applies it in one transaction; `RejectDraft` keeps it with a required reason. Drafter, decider, `decided_at`, note and result are on the draft; applied changes' revisions name the approver and link the draft (`legislation_revisions.draft_id`). Who may decide: the gate `approval.gate` (`approve-legislation`), which the app defines; null skips the check. `DraftSubmitted`/`DraftDecided` events. Approval off, or `$action->directly()`, applies at once (tests, seeders, imports). georgian-tax: a test that a drafted VAT change does nothing until approved. tax-legislation 6c43eb4, georgian-tax (this commit).

- 2026-11-02: **[MVP] Per-country bindings.** tax-legislation's `Rates\CountryTables` keeps rates, thresholds and tables per country (scoped, once per request; an unregistered country reads its recorded history). `Tax::rateTable()`, `thresholdTable()`, `tableSource()` read the tax's own country, and a type-hinted `RateTable`/`ThresholdTable`/`TableSource` in a stage resolves to the assessing tax's country. georgian-tax registers `GE` instead of binding global singletons. Tested with invented Armenian and Azerbaijani taxes sharing article keys, and a second country beside Georgia. tax-legislation 9d5f500, georgian-tax (this commit).
- 2026-11-02: Added the Tax Code text (`docs/law`), the sandbox setup (`tools/sandbox`) and this roadmap. Daily sessions start.
- 2026-11-02: The owner answered the five questions: approval required, JSON API, roles deferred, past law left to a future agent, tag once the MVP is done.
