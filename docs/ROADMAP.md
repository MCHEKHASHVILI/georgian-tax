# Roadmap

The working list for the four packages: [money](https://github.com/MCHEKHASHVILI/money), [countries](https://github.com/MCHEKHASHVILI/countries), [tax-legislation](https://github.com/MCHEKHASHVILI/tax-legislation), [georgian-tax](https://github.com/MCHEKHASHVILI/georgian-tax). A daily session takes the top unblocked item, does it, and updates this file in the same push. The owner can reorder it, add items or answer questions here at any time.

## Questions for the owner

A question stays here until it is answered. Items that depend on it are blocked until then.

1. **Drafts and approval.** Should an amendment entered by an agent (or a junior person) be a draft that someone approves before it takes effect? Or should every save be live, with the revision log as the only check?
2. **Who may edit what.** Should the package ship roles or permissions (for example, editors per country or per tax), or leave authorization entirely to the app's policies?
3. **Forms UI.** Should the package include the forms people use to enter the law? Options: a Filament panel, Blade/Livewire views, a JSON API for a separate frontend (Vue/React), or none.
4. **Past Georgian law.** When should earlier periods be entered (the 2004 Code, earlier rates and thresholds)? Which sources: Matsne's consolidated historical versions, or the amending laws themselves?
5. **Release.** The v1.0.0 tags and the Packagist submission have to be done from GitHub/Packagist by the owner, because pushes of tags are refused from the sandbox. When should that happen?

## Next

In order. Skip anything blocked by an open question.

1. **Per-country bindings in the engine.** `RateTable`, `ThresholdTable` and `TableSource` are global singletons today, so two country packages in one app would collide. Resolve them per tax (by `country()`), for example with contextual bindings or a registry keyed by country, and test this with a second invented country.
2. **Store the country on assessments.** Add a `country` column to `tax_assessments`, filled from the taxes that ran.
3. **The remaining hard-coded law → thresholds and tables:**
   - personal income tax relief allowances (82.2);
   - ImportRelief limits (GEL caps per relief);
   - excise rule constants: cars and motorcycles per cm³ by age, tobacco, alcohol, calls (188.1, 188.3);
   - profit tax limits: charity 10% and representation costs 1%;
   - municipal property rate caps and base rates (204, 202).

   Each becomes a key in `resources/law/*.php` and in `GeorgiaLegislationSeeder`, with a test that amending it in the DB changes the result.
4. **Refresh `CATALOGUE.md`** against the code: articles, thresholds, exemptions.
5. **Features the README lists as not modelled**, one per session, each with tests citing the article:
   - 197.4–5: temporary admission at 3% a month;
   - 189: excise credit;
   - 189.8 and 198.3: refunds;
   - 98.4: banks;
   - 177: VAT apportionment;
   - 205.3: property tax instalments.
6. **Check the Code for amendments.** Compare the articles the code cites against the latest consolidated text on matsne.gov.ge. Record each change as an amendment through the actions, in both the law files and the seeder, citing the amending law.

## Done

Newest first. One line per session: the date, what changed, and the commits.

- 2026-11-02: Added the Tax Code text (`docs/law`), the sandbox setup (`tools/sandbox`) and this roadmap. Daily sessions start.
