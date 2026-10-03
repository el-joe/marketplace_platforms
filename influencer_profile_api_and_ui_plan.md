# Influencer Profile — API `type` Filter & UI Plan

## Codebase Analysis

### Key Files

| File | Role |
|------|------|
| `backend/routes/api_customer_v1.php` | Registers `GET /catalog-listings/` → `ListingController::index()` |
| `backend/app/Http/Controllers/Api/Customer/ListingController.php` | Handles catalog listing index + detail |
| `backend/app/Services/Customer/ListingQueryService.php` | Shared query builders for VendorListing, AdminListing, MarketerListing |
| `backend/app/Http/Controllers/Customer/BrowseController.php` | Category browse — uses ListingQueryService |
| `backend/app/Http/Controllers/Api/Public/MarketerProfileController.php` | Marketer profile page API (already upgraded in prior session) |

### Current `GET /catalog-listings/` Behavior

```
isNawyNow = true  → AdminListing query (platform stock)
isNawyNow = false → VendorListing query + MarketerListing dedup
```

No `type` param exists. There is no way for a client to request "only vendor listings" or "only marketer listings".

### What Needs to Change

Add `?type=admin_listing|vendor_listing|marketer_listing` to `ListingController::index()`.

Additionally, `type=marketer_listing` needs a `marketer_id` scope param so the marketer profile UI can fetch tab-specific pages through this unified endpoint if desired (alternative: use existing `/marketers/{slug}` endpoint — see decision below).

---

## Business Logic per `type` Value

| `type` value      | Model queried    | Auth/Guard      | Extra params supported     |
|-------------------|------------------|-----------------|---------------------------|
| `admin_listing`   | `AdminListing`   | public          | `category_id`, `min_price`, `max_price` |
| `vendor_listing`  | `VendorListing`  | public          | `category_id`, `min_price`, `max_price`, `condition`, `sort` |
| `marketer_listing`| `MarketerListing`| public          | `category_id`, `marketer_id`, `min_price`, `max_price` |
| _(none)_          | current behavior | public          | unchanged                  |

---

## Execution Plan

### Task 1 — Backend: Add `type` filter to `ListingController::index()`

**File:** `backend/app/Http/Controllers/Api/Customer/ListingController.php`

Changes:
1. Parse `?type` from request (validate against allowed values)
2. Branch: when `type` is set, route to the correct query builder
3. Add `buildMarketerQuery()` private method (scopes MarketerListing, supports `marketer_id`, `category_id`, price range)
4. Return `MarketerListingResource` items for marketer type
5. Git commit

### Task 2 — Frontend: Update marketer profile tabs to use correct data

The marketer profile page already has its own `/marketers/{slug}` endpoint that returns all 3 listing sections (own, vendor_campaign, marketer_campaign). The tabbed UI built in the prior session correctly uses that endpoint.

The `type` filter on `/catalog-listings/` serves a **different use case**: general catalog browsing filtered by listing source. The marketer profile does NOT need to switch to `/catalog-listings/` — the dedicated endpoint is more efficient (returns all sections in one request).

The frontend impact is therefore:
- Document the new API capability in a type declaration update
- No change needed to marketer profile tabs (already correct)

### Task 3 — Git commit per task (enforced)

After each task, `git add . && git commit -m "feat: ..."`.
