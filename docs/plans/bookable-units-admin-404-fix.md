# Admin Bookable Units 404 — Fix Plan

**Date:** 2026-09-29  
**Symptom:** Admin visits `admin.noon.codefanz.com/travel-agencies/bookable-units` → 404 Not Found

---

## Root Cause

The correct admin URL is `/travel/bookable-units` (route name: `admin.travel.bookable-units.index`).  
The admin typed `/travel-agencies/bookable-units` — that path does not exist.

The backend routes, controller, and views are all correct. The NavigationService already has a nav link pointing to the right route. The issue is **discoverability**: the admin doesn't know the correct URL and the sidebar nav link may not be visible/clickable yet.

---

## Tasks

### Sub-agent 1 — Add redirect from wrong URL to correct one (defensive)

**File:** `backend/routes/admin.php`

Add a redirect so that `/travel-agencies/bookable-units` (and `/travel-agencies/bookable-units/*`) redirects to the correct path:

```php
Route::redirect('/travel-agencies/bookable-units', '/travel/bookable-units')->name('travel-agencies.bookable-units.redirect');
Route::redirect('/travel-agencies/bookable-units/{any}', '/travel/bookable-units/{any}')->where('any', '.*');
```

Place this near the top of the authenticated admin route group (after the middleware group opens).

---

### Sub-agent 2 — Verify the admin sidebar nav link renders correctly

The NavigationService already has:
```php
'route' => 'admin.travel.bookable-units.index',
```

Check that:
1. The nav link is inside the correct Travel group in `NavigationService.php`
2. The rendered sidebar HTML actually generates the href `/travel/bookable-units`
3. Run `php artisan route:list --name=admin.travel.bookable-units` to confirm the route resolves

Report the actual generated URL.

---

### Sub-agent 3 — Verify the full admin approve flow end-to-end

1. Confirm `GET /travel/bookable-units` → index view renders (status 200)
2. Confirm `GET /travel/bookable-units/{id}` → show view renders with Approve/Reject buttons
3. Confirm `POST /travel/bookable-units/{id}/approve` → sets status to `active`, redirects back with success flash
4. Run existing tests: `php artisan test --filter=BookableUnitAdmin`

Report pass/fail.

---

## Files Involved

| File | Change |
|------|--------|
| `backend/routes/admin.php` | Add redirect from `/travel-agencies/bookable-units` → `/travel/bookable-units` |
| `backend/app/Services/NavigationService.php` | Verify only (already correct) |

---

## Non-issues (already confirmed correct)

- Route `admin.travel.bookable-units.index` → `GET /travel/bookable-units`: ✅ registered  
- `AdminBookableUnitController::index()` method: ✅ exists  
- Admin blade views (index + show + approve/reject buttons): ✅ exist  
- NavigationService nav entry with badge: ✅ added in previous session  
