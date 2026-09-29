# Bookable Units Visibility Fix

**Date:** 2026-09-29  
**Reporter:** Travel Agency Admin  
**Symptom:** Bookable units added via the Travel Agency dashboard never appear on the customer-facing website, even after agency staff add them.

---

## Root Cause Analysis

### Bug 1 — API returns units regardless of approval status (CRITICAL)

**File:** `backend/app/Http/Resources/Customer/TravelPackageDetailResource.php`  
**Line:** ~62 (`bookable_units` key)

```php
// CURRENT (broken) — returns ALL units for the agency, including drafts/rejected
'bookable_units' => $agency
    ? $agency->bookableUnits()->orderBy('name')->get()->map(...)
    : [],

// FIXED — only return approved/active units
'bookable_units' => $agency
    ? $agency->bookableUnits()->where('status', 'active')->orderBy('name')->get()->map(...)
    : [],
```

The admin approval flow **does exist** (Admin panel → Travel → Bookable Units → Approve/Reject). When an admin approves a unit its `status` flips from `draft` → `active`. But the API that serves the package detail page never filters by `status`, so customers see nothing because the frontend guards with `pkg.bookable_units?.length > 0` and the units shown are in draft state — and the calendar API itself returns 404 for non-active units, making the section silently empty.

**Wait — actually the opposite:** the frontend receives the units (draft ones), renders the `<BookableUnitsSection>`, then when the calendar is fetched for a draft unit the backend returns 404 → the calendar is null → the date grid is empty. The section header still renders, but the booking widget is broken/empty. Either way, the fix is to filter `status = 'active'` in the resource.

---

## Tasks

### Sub-agent 1 — Backend API fix (5 min)

**File to edit:** `backend/app/Http/Resources/Customer/TravelPackageDetailResource.php`

Change line in `bookable_units`:
```php
$agency->bookableUnits()->orderBy('name')->get()
```
to:
```php
$agency->bookableUnits()->where('status', 'active')->orderBy('name')->get()
```

**Verify:** The existing test `backend/tests/Feature/BookableUnitAdminTest.php` should cover the approve flow. Also confirm `BookableUnitAvailabilityController::calendar()` already guards with `->where('status', 'active')` ✓ (it does).

---

### Sub-agent 2 — Admin panel: add "Pending Approval" badge/count to nav (optional UX, low effort)

The admin can already approve units via the show page. The index listing already shows STATUS column. No structural change needed — this is working. 

**Optional improvement:** Add a pending-count badge to the admin sidebar Travel nav item, similar to how other sections show counts. This is cosmetic; skip for MVP fix.

---

### Sub-agent 3 — Verify end-to-end flow

1. Create a bookable unit via Travel Agency portal → status = `draft`
2. Confirm it does NOT appear on customer site (after the fix)
3. Admin approves it → status = `active`
4. Confirm it NOW appears on customer site with working calendar

---

## Files Involved

| File | Change |
|------|--------|
| `backend/app/Http/Resources/Customer/TravelPackageDetailResource.php` | Add `->where('status', 'active')` filter |
| `backend/tests/Feature/BookableUnitAdminTest.php` | Read to verify coverage |

---

## Non-issues (already correct)

- Admin approve/reject routes: ✅ exist at `POST /admin/travel/bookable-units/{unit}/approve`
- Admin views (index + show with approve/reject buttons): ✅ exist
- Customer calendar API filter: ✅ already guards `where('status', 'active')`  
- Frontend section: ✅ correctly hides when `units.length === 0`
- The **only** missing piece is the resource not filtering approved units

---

## Fix Scope

**1 line change.** Everything else in the system (admin UI, approval logic, customer booking flow) is already built and working correctly.
