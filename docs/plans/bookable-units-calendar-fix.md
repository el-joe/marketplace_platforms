# Bookable Units — Calendar Broken + Wrong Place Fix

**Date:** 2026-09-29  
**URL:** `noon.codefanz.com/uae-en/travel/test-package-1-jvvxeg`  
**Symptoms:**
- Calendar is completely empty (no day cells)
- Month shows "2026-07" (should be current month)
- Total Price shows "Ð0" (broken currency)
- Units appear on ALL agency packages (wrong — should be per-package)

---

## Root Cause Analysis

### Issue 1 — Calendar empty (CRITICAL)

`BookableUnitsSection` fetches `GET /bookable-units/{unit}/calendar?month=YYYY-MM`.  
The API does `BookableUnit::where('status', 'active')->find($unit)` — if the unit is `draft`, it returns 404.  
The frontend `.catch(() => setCalendar(null))` silently swallows the error → `calendar` stays `null` → `calendar?.days.map(...)` renders nothing → empty grid.

**On production:** The unit is still `draft` because:
1. The admin approval URL was broken (404) — fixed in prior session
2. No admin has approved it yet

**Fix 1a:** Admin must approve the unit on production via `/travel/bookable-units`.  
**Fix 1b (UX):** The component must show an error/loading state when calendar is null, not a completely blank section. Currently there is no visual feedback at all when the calendar fails to load.

### Issue 2 — "2026-07" month (stale/past)

The component initializes with `useState(() => new Date())` which is correct. The screenshot likely captured a past state or the clock on the test server differs. Minor — not a code bug. No fix needed beyond verifying on a fresh page load.

### Issue 3 — "Ð0" currency

The `Price` component uses a custom `currency-font` where characters map to currency symbols. "Ð" is the AED glyph in that font. When `total = 0` (no dates selected yet), it correctly shows `Ð0` in the currency font — this looks broken in the screenshot only because no date is selected and the font may not have loaded. **Not a code bug**, but UX can be improved by hiding the total row until dates are selected.

### Issue 4 — Units appear on ALL agency packages (WRONG PLACE)

`TravelPackageDetailResource` returns `$agency->bookableUnits()->where('status','active')` — ALL active units for the agency appear on EVERY package that agency owns. This is architecturally wrong: a chalet booking is a separate product and should only appear on packages where the agency explicitly links it.

**Fix:** Add a nullable `travel_package_id` FK to `bookable_units`, and filter in the resource:
```php
$agency->bookableUnits()->where('status','active')->where('travel_package_id', $this->id)
```
The travel agency portal must allow linking/unlinking units to packages.

---

## Tasks

### Sub-agent 1 — Frontend: Fix calendar empty state + UX improvements

**File:** `frontend/src/features/flights/package-details/components/bookable-units-section.tsx`

Changes:
1. When `calendar` is `null` and `loading` is `false`, show a message: "Calendar unavailable — please try again later" (use `t("calendarUnavailable")` key)
2. Add a loading skeleton while `loading === true` (simple grey placeholder grid)
3. Hide the "Total Price" row (`Ð0` display) when `total === 0` and no dates are selected
4. Add the i18n key `calendarUnavailable` to both locale files (`locale/en.json` and `locale/ar.json` under the `flights.packageDetails` namespace)

Do NOT change the booking logic — only add the missing states and hide the zero-price row.

---

### Sub-agent 2 — Backend: Link bookable units to specific packages

**Step A — Migration:**
```bash
php artisan make:migration add_travel_package_id_to_bookable_units --table=bookable_units
```

Add a nullable `travel_package_id` UUID FK to `bookable_units`:
```php
$table->uuid('travel_package_id')->nullable()->after('travel_agency_id');
$table->foreign('travel_package_id')->references('id')->on('travel_packages')->nullOnDelete();
```
Run `php artisan migrate`.

**Step B — Update `TravelPackageDetailResource`:**

File: `backend/app/Http/Resources/Customer/TravelPackageDetailResource.php`

Change:
```php
$agency->bookableUnits()->where('status', 'active')->orderBy('name')->get()
```
To:
```php
$agency->bookableUnits()
    ->where('status', 'active')
    ->where('travel_package_id', $this->id)
    ->orderBy('name')
    ->get()
```

**Step C — Travel Agency Portal: add package-link UI**

File: `backend/resources/views/travel-agency/bookable-units/show.blade.php`

Add a form section "Link to Package" that lets the agency select a travel package from their portfolio and save it:
- `POST /{bookableUnit}/link-package` with `travel_package_id` (nullable)
- Route + controller method `linkPackage(Request $request, BookableUnit $unit)` in `TravelAgencyPortal/BookableUnitController`
- Only packages owned by the same agency shown in the select

**Step D — Update `BookableUnit` model:**
Add `travel_package_id` to `$fillable`.

Run tests: `php artisan test --filter=BookableUnit`

---

### Sub-agent 3 — Verify end-to-end after both fixes

1. Confirm the migration ran: `php artisan migrate:status | grep bookable`
2. Confirm the resource now returns 0 units for packages where no unit is explicitly linked
3. Confirm the frontend i18n keys exist
4. Run `php artisan test --filter=BookableUnit` and `php artisan test --filter=TravelPackage`
5. Run pint: `vendor/bin/pint --dirty --format agent`

Report results.

---

## Files Involved

| File | Change |
|------|--------|
| `frontend/.../bookable-units-section.tsx` | Add null state, loading state, hide Ð0 |
| `frontend/locale/en.json` | Add `calendarUnavailable` key |
| `frontend/locale/ar.json` | Add Arabic translation |
| `backend/database/migrations/..._add_travel_package_id_to_bookable_units.php` | New migration |
| `backend/app/Models/BookableUnit.php` | Add `travel_package_id` to `$fillable` |
| `backend/app/Http/Resources/Customer/TravelPackageDetailResource.php` | Filter by `travel_package_id` |
| `backend/app/Http/Controllers/TravelAgencyPortal/BookableUnitController.php` | Add `linkPackage()` method |
| `backend/routes/travel.php` | Add `link-package` route |
| `backend/resources/views/travel-agency/bookable-units/show.blade.php` | Add link-to-package form |
