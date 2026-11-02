# Roadmap

The working list for the four packages: [money](https://github.com/MCHEKHASHVILI/money), [countries](https://github.com/MCHEKHASHVILI/countries), [tax-legislation](https://github.com/MCHEKHASHVILI/tax-legislation), [georgian-tax](https://github.com/MCHEKHASHVILI/georgian-tax). A daily session takes the top unblocked item, does it, and updates this file in the same push. The owner can reorder it, add items or answer questions here at any time.

## Questions for the owner

A question stays here until it is answered. Items that depend on it are blocked until then.

_None open._

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

1. **[MVP] Per-country bindings in the engine.** `RateTable`, `ThresholdTable` and `TableSource` are global singletons today, so two country packages in one app would collide. Resolve them per tax by its `country()`, for example with contextual bindings or a registry keyed by country, and test with a second invented country.
2. **[MVP] Drafts and approval (tax-legislation).** Through the actions, an amendment, repeal, new tax type or legal act is saved as a draft (pending). It has no legal effect, so the engine, `inForceOn` and `Law` ignore it, until someone approves it.
   - Approving it applies it in a transaction: the period is closed and opened, overlaps are re-checked at that moment, and the approver is recorded.
   - Rejecting it keeps the record, with the reason.
   - Revisions record who drafted, who approved or rejected, and why.
   - The app decides who may approve, through a gate or policy hook. Do not ship roles: the owner deferred that.
   - Config can switch approval off so saves take effect directly (tests and seeders).
   - Seeders and imports take effect directly.
3. **[MVP] JSON API (tax-legislation).** Optional routes, behind a config switch with a prefix and middleware:
   - read: countries, legal acts, tax types, and values in force on a date, with their history;
   - write: the actions, creating drafts;
   - approve or reject drafts;
   - the revision log.

   Use API Resources, return validation errors as 422, and leave authentication to the app's middleware. Test it with Testbench HTTP tests.
4. **[MVP] The remaining hard-coded law → thresholds and tables:**
   - personal income tax relief allowances (82.2);
   - ImportRelief limits (GEL caps per relief);
   - excise rule constants: cars and motorcycles per cm³ by age, tobacco, alcohol, calls (188.1, 188.3);
   - profit tax limits: charity 10% and representation 1%;
   - municipal property rate caps and base rates (204, 202).

   Each becomes a key in `resources/law/*.php` and `GeorgiaLegislationSeeder`, with a test that amending it changes the result.
5. **[MVP] Store the country on assessments.** Add a `country` column to `tax_assessments`.
6. **[MVP] Refresh READMEs and `CATALOGUE.md`** against the code. When this is done, report to the owner that the MVP is ready to tag.
7. **Features the README lists as not modelled**, one per session, each with tests citing the article:
   - 197.4–5: temporary admission at 3% a month;
   - 189: excise credit;
   - 189.8 and 198.3: refunds;
   - 98.4: banks;
   - 177: VAT apportionment;
   - 205.3: property tax instalments.
8. **Check the Code for amendments.** Compare the cited articles with the latest consolidated text on matsne.gov.ge, and record each change through the actions (in both the files and the seeder), citing the amending law.

## Done

Newest first. One line per session: the date, what changed, and the commits.

- 2026-11-02: Added the Tax Code text (`docs/law`), the sandbox setup (`tools/sandbox`) and this roadmap. Daily sessions start.
- 2026-11-02: The owner answered the five questions: approval required, JSON API, roles deferred, past law left to a future agent, tag once the MVP is done.
