# Migrate COD Limits from Global Settings → Per-Country Settings

## Current state (confirmed by code read, 2026-09-20)

- Three keys live in the generic key/value `settings` table (`category='orders'`), inserted by
  `backend/database/migrations/2026_09_12_173000_add_cod_limit_settings.php`:
  `cod_global_max_amount`, `cod_supermall_max_amount`, `cod_supermall_category_id`.
- They render automatically in the generic admin Settings page
  (`backend/resources/views/admin/settings/partials/orders.blade.php`) — no dedicated UI code.
- The only place that hardcodes these 3 keys by name is
  `backend/app/Services/SettingsService.php::validateGroup()` (~line 200-230).
- Enforcement engine: `backend/app/Services/Customer/CodValidationService.php::validate()`.
  Reads `Setting::get('cod_global_max_amount', 0)` / `cod_supermall_max_amount` /
  `cod_supermall_category_id`, has zero notion of country for the *limit* values today (it only
  takes `$destinationCountryId` for the separate international-block check).
- Called from:
  - `backend/app/Http/Controllers/Api/Customer/CheckoutController.php` (~line 74, payment-methods list)
  - `backend/app/Http/Controllers/Customer/CheckoutController.php` (~line 367 preview, ~line 773 place-order)
- Nawi/admin-listing exemption is structural (`$item->adminListing !== null` skip), country-agnostic,
  **no change needed**.
- International auto-block is already per-line/per-country via `CartLineSource::isInternational()`,
  **no change needed**.
- `countries` table already has a **separate**, working per-country boolean gate: `cod_available`
  (`backend/app/Models/Country.php`, admin CRUD in `backend/app/Http/Controllers/Admin/CountryController.php`,
  form partial `backend/resources/views/admin/countries/_form.blade.php` ~line 291-295).
- Existing regression tests: `backend/tests/Feature/Checkout/CodLimitsTest.php`.

## Target architecture

Add the limit fields onto `countries` (same table as `cod_available`, since these are 1:1 per-country
config, not history that needs versioning):

- `cod_max_amount` (unsigned int, default 0 = unlimited)
- `cod_supermall_max_amount` (unsigned int, default 0 = unlimited)
- `cod_supermall_category_id` (nullable uuid FK → categories.id)

`CodValidationService::validate()` starts requiring `$destinationCountryId`, loads the `Country`, and
reads the three values off it instead of `Setting::get(...)`.

### Hard constraints (from project memory — do not violate)

- **Never delete/rollback/edit the existing migration**
  `2026_09_12_173000_add_cod_limit_settings.php`. Removing the old settings rows must be a **new**
  migration that deletes those 3 rows (or leaves them as inert legacy data — prefer deleting the rows,
  never the migration file).
- **Never run `schema:dump --prune`** — regenerate/update the schema dump without that flag if a dump
  step is part of the workflow, or skip dumping and let the normal migration flow handle it.

## Sub-agent task breakdown

Run these mostly in order (1 → 2 → {3,4} → 5 → 6). Each prompt below is self-contained — paste it
directly into a new subagent/session.

---

### Agent 1 — Migration + Model: add per-country COD columns

```
Add per-country COD-limit configuration to the `countries` table in the Laravel app at
/var/www/marketplace/backend.

Context: `countries` already has a `cod_available` boolean column (see
backend/app/Models/Country.php and the schema at backend/database/schema/mysql-schema.sql). We are
adding three new columns to sit alongside it:
- cod_max_amount (unsigned integer, not null, default 0 — 0 means unlimited)
- cod_supermall_max_amount (unsigned integer, not null, default 0 — 0 means unlimited)
- cod_supermall_category_id (nullable char(36)/uuid, foreign key to categories.id, nullable on
  delete set null — 0/empty means "supermall limit not configured for this country")

Tasks:
1. Create a NEW migration file (timestamp it after the most recent migration in
   backend/database/migrations/) that adds these 3 columns to `countries` via Schema::table. Do NOT
   toutouch, edit, or delete any existing migration file — this is a hard project rule (this is a
   live project; migrations already run in other environments).
2. In the same or a second new migration, DELETE the 3 rows from the `settings` table with keys
   `cod_global_max_amount`, `cod_supermall_max_amount`, `cod_supermall_category_id` (category
   'orders'). Again: do not edit or delete the migration that originally inserted them
   (backend/database/migrations/2026_09_12_173000_add_cod_limit_settings.php) — leave it as-is,
   just add a new migration that removes the rows at runtime.
3. Update backend/app/Models/Country.php: add the 3 new columns to $fillable and appropriate casts
   (integers for the two amounts; the category id as a plain string/uuid cast). Add a
   `codSupermallCategory()` belongsTo relation to the Category model if useful for eager loading.
4. Run `php artisan migrate` locally to verify the migrations apply cleanly, and run
   `php artisan migrate:rollback --step=1` twice to verify they're reversible (down() methods),
   then re-migrate.
5. Do NOT run `php artisan schema:dump --prune` — if you need to refresh the schema dump, run it
   WITHOUT --prune, or skip that step and note it needs a manual dump refresh later.

Report back which migration files you created and the final column list you added.
```

---

### Agent 2 — Service refactor: CodValidationService reads per-country limits

```
Refactor the COD-limit enforcement logic in the Laravel app at /var/www/marketplace/backend to read
limits from the `countries` table instead of the global `settings` table.

Prerequisite: Agent 1 has already added `cod_max_amount`, `cod_supermall_max_amount`, and
`cod_supermall_category_id` columns to the `countries` table and to
backend/app/Models/Country.php's $fillable/casts. Confirm those exist before starting; if they
don't, stop and say so rather than re-adding them.

Files to change:
1. backend/app/Services/Customer/CodValidationService.php — currently `validate(array $cartItems,
   ?string $destinationCountryId = null)` reads `Setting::get('cod_global_max_amount', 0)`,
   `Setting::get('cod_supermall_max_amount', 0)`, and `Setting::get('cod_supermall_category_id', '')`
   (see the `supermallLftRgtRange()` helper). Change it to:
   - Make `$destinationCountryId` a required, non-nullable parameter (it's always passed by both
     call sites already — verify this before tightening the signature).
   - Load the `Country` model for that id (fail gracefully / treat as "no limit configured" if the
     country isn't found, matching current default-0-means-unlimited behavior).
   - Replace the three `Setting::get(...)` calls with `$country->cod_max_amount`,
     `$country->cod_supermall_max_amount`, `$country->cod_supermall_category_id`.
   - Leave the Nawi/admin-listing exemption logic (`$item->adminListing !== null` skip) and the
     international-block logic untouched — those are correct as-is and country-agnostic /
     already-per-country respectively.
2. backend/app/Http/Controllers/Api/Customer/CheckoutController.php (~line 74) and
   backend/app/Http/Controllers/Customer/CheckoutController.php (~line 367, ~line 773) — these call
   `$this->codValidationService->validate($cartItems, $country->id)`. Confirm a resolved `$country`
   (or country id) is genuinely in scope at all three call sites before the signature tightening in
   step 1; if any call site doesn't have one, fix that call site to resolve it first rather than
   loosening the service signature back to nullable.
3. backend/app/Services/SettingsService.php — remove the hardcoded special-case validation for
   `cod_global_max_amount`, `cod_supermall_max_amount`, `cod_supermall_category_id` in
   `validateGroup()` (~line 200-230), since these keys no longer exist in the settings table after
   Agent 1's cleanup migration runs.

Do not touch database migrations — that's Agent 1's job. Run
`php artisan test --filter=CodValidationService` or equivalent if such a unit test exists, otherwise
just verify with `php artisan tinker` that `CodValidationService::validate()` still type-checks and
runs against a seeded country.

Report back the diff summary and any call site where the country wasn't already resolved.
```

---

### Agent 3 — Admin UI: country form gets COD-limit fields

```
Add admin UI for the new per-country COD-limit fields in the Laravel app at
/var/www/marketplace/backend. Prerequisite: Agent 1 has added `cod_max_amount`,
`cod_supermall_max_amount`, `cod_supermall_category_id` columns to `countries` and to
backend/app/Models/Country.php. Confirm before starting.

The admin country CRUD lives in:
- backend/app/Http/Controllers/Admin/CountryController.php — `store()` (~line 118-133) and
  `update()` (~line 176-192) each have an explicit `$request->validate([...])` array listing every
  fillable field, including `cod_available`. Add validation rules for the 3 new fields:
  - cod_max_amount: nullable|integer|min:0
  - cod_supermall_max_amount: nullable|integer|min:0
  - cod_supermall_category_id: nullable|uuid|exists:categories,id
  Make sure the validated values are actually passed through to Country::create()/update() (check
  how `cod_available` currently flows through — mirror that pattern).
- backend/resources/views/admin/countries/_form.blade.php — the `cod_available` toggle is at
  ~line 291-295. Add form inputs directly below it for the 3 new fields: two numeric inputs (COD max
  amount, Supermall COD max amount — label them clearly, 0/blank = unlimited) and a category picker
  (select or typeahead, sourced from the Category model) for the Supermall category. Match the
  existing Blade component conventions used elsewhere in this form (look at how `vat_rate` or another
  numeric field is rendered for the input pattern, and how any other category-select is rendered
  elsewhere in the admin if one exists).
- backend/resources/views/admin/countries/index.blade.php and the `columnDefinitions()` method in
  CountryController — optionally add the new fields as datatable columns (follow the existing
  `cod_available` column pattern at ~line 20-21), but this is lower priority than the form itself —
  do it only if time permits.
- backend/app/Services/CountryService.php — check `validateForLaunch()` and related methods; decide
  whether launching a country should require COD-limit fields to be set (likely NOT required, since
  0 = unlimited is a valid default) — leave launch validation alone unless there's an obvious existing
  pattern requiring it for `cod_available`.

Also, since the 3 old global settings keys are being removed from the `settings` table (Agent 1's
migration), verify backend/resources/views/admin/settings/partials/orders.blade.php still renders
correctly with those rows gone (it's a generic @foreach loop over whatever's in the category, so it
should just show one fewer row — no template change should be needed, but check the "orders" tab
isn't left completely empty in a way that breaks the tab UI, and if it would be empty, check whether
that's fine or needs the tab hidden).

Report back which files you changed and include a screenshot-equivalent description (or the rendered
HTML snippet) of the new form fields if you can verify the page renders.
```

---

### Agent 4 — Tests: update CodLimitsTest for per-country config

```
Update the COD-limit feature tests in the Laravel app at /var/www/marketplace/backend to match the
new per-country configuration model. Prerequisite: Agents 1 and 2 have already (a) added
cod_max_amount / cod_supermall_max_amount / cod_supermall_category_id columns to `countries`, and
(b) changed backend/app/Services/Customer/CodValidationService.php to read limits from the
destination Country instead of the global `settings` table. Confirm both are done before starting.

File: backend/tests/Feature/Checkout/CodLimitsTest.php — read it fully first. It currently seeds
global settings rows (cod_global_max_amount, cod_supermall_max_amount, cod_supermall_category_id) via
whatever helper it uses (likely `Setting::updateOrCreate` or a factory) to set up each test scenario.

Rewrite every test's setup to instead set these values directly on the relevant `Country` model
(country_id used to build the test cart/checkout), e.g.
`$country->update(['cod_max_amount' => 50000, ...])` or via a Country factory state if one exists or
is worth adding (check backend/database/factories/CountryFactory.php).

Keep the actual assertions (expected error codes: cod_limit_exceeded, cod_supermall_limit_exceeded,
cod_international_not_allowed, cod_unavailable, etc.) unchanged — only the setup/arrange step for
which "account" the limit lives on should change, since the enforcement behavior itself must stay
identical.

Also grep the whole backend/tests directory for any other test referencing
`cod_global_max_amount`, `cod_supermall_max_amount`, or `cod_supermall_category_id` as a Setting key,
and update those too.

Run `php artisan test --filter=CodLimitsTest` (and any other cod-related test files found) until
green. Report back the before/after test count and any test you had to meaningfully rewrite (not just
mechanically translate).
```

---

### Agent 5 (optional, run last) — Cleanup sweep

```
Do a final consistency sweep in the Laravel app at /var/www/marketplace/backend after the COD-limit
settings have been migrated from the global `settings` table to per-country columns on `countries`
(prior agents added cod_max_amount/cod_supermall_max_amount/cod_supermall_category_id to Country,
updated CodValidationService to read from Country, removed the special-case validation in
SettingsService, added admin form fields, deleted the 3 old settings rows via a new migration, and
updated CodLimitsTest.php).

Grep the whole backend/ directory (excluding vendor/, storage/, and any .claude/worktrees/ paths) for
the literal strings `cod_global_max_amount`, `cod_supermall_max_amount`, and
`cod_supermall_category_id` used as Setting keys (i.e. inside `Setting::get(...)`,
`Setting::set(...)`, migration seed data, or config arrays) — every remaining hit should either be:
(a) inside the original, untouched migration
backend/database/migrations/2026_09_12_173000_add_cod_limit_settings.php (leave it), or
(b) inside the new cleanup migration that deletes those rows (leave it).
Any other hit is stale code that still assumes the global-settings model and needs fixing.

Also check backend/app/Enums/SettingCategory.php (or wherever the 'orders' settings category is
defined) for any leftover reference, and check whether any i18n/lang file (search for
`cod_limit_exceeded`, `cod_supermall_limit_exceeded`, `cod_international_not_allowed`,
`cod_unavailable`) needs a wording tweak now that the limit is configured per-country rather than
globally (e.g. "current limit for your country is X" instead of "global limit is X") — this is a UX
nice-to-have, apply only if a translation string literally says "global".

Do NOT run `php artisan schema:dump --prune` at any point — if a schema dump needs refreshing, run it
without --prune, or leave it for a human to do.

Report back a clean/dirty verdict and list every file you touched.
```

---

## Suggested execution order

1. **Agent 1** (migration + model) — must land first, everything else depends on the columns existing.
2. **Agent 2** (service refactor) — depends on Agent 1.
3. **Agent 3** (admin UI) and **Agent 4** (tests) can run in parallel once Agent 2 is done.
4. **Agent 5** (cleanup sweep) last, after all others have merged.

Run `php artisan migrate` and the full `CodLimitsTest` suite after each stage before moving to the
next, and do a manual smoke test in the admin country edit form + a storefront checkout with COD
before calling this done.
