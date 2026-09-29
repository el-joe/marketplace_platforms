# Fix: Bookable Unit "Available" Checkbox Not Persisting

## Problem

When a travel-agency user unchecks "Available" for a calendar day and clicks Save, the day always stays available. This happens in both the per-day save and the bulk-range save.

## Root Cause

HTML checkboxes submit **no field at all** when unchecked. The controller reads:

```php
$isAvailable = $request->boolean('is_available', true);
```

Because the default is `true`, an unchecked box (which sends nothing) is always interpreted as "available". The row is upserted with `is_available = 1` every time.

## Fix (two files)

### 1. `backend/resources/views/travel-agency/bookable-units/show.blade.php`

Add a hidden `<input type="hidden" name="is_available" value="0">` **before** every checkbox that controls `is_available`. The browser submits the hidden field first; if the checkbox is checked it also submits `1`, which overrides `0` (last value wins in PHP). If unchecked, only `0` is submitted.

Fix needed in two places inside this view:
- Per-day grid row checkbox (line ~94)
- Bulk-range form checkbox (line ~137)

### 2. `backend/app/Http/Controllers/TravelAgencyPortal/BookableUnitController.php`

With the hidden field always present, the default fallback is no longer needed. Remove the `true` default so an explicit `0` is honoured:

```php
// Before
$isAvailable = $request->boolean('is_available', true);

// After
$isAvailable = $request->boolean('is_available');
```

Fix in both `upsertAvailability()` (line ~204) and `bulkUpsertAvailability()` (line ~240).

## Sub-agent Prompt

> **Task:** Fix the "Available" checkbox in the bookable-unit calendar so that unchecking and saving a day correctly persists `is_available = false`.
>
> **Files to touch:**
> - `backend/resources/views/travel-agency/bookable-units/show.blade.php`
>   - In the per-day table row form, add `<input type="hidden" name="is_available" value="0">` immediately before the `<input type="checkbox" name="is_available" …>` line (~line 94).
>   - In the bulk-range form, do the same before its checkbox (~line 137).
> - `backend/app/Http/Controllers/TravelAgencyPortal/BookableUnitController.php`
>   - Change `$request->boolean('is_available', true)` → `$request->boolean('is_available')` in both `upsertAvailability()` and `bulkUpsertAvailability()`.
>
> **Verification:** After the fix, toggling a day to unavailable and saving must set `is_available = 0` in `bookable_unit_availabilities`. Reloading the page must show the checkbox unchecked.

## No schema changes needed.
