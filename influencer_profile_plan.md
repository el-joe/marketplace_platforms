# Influencer Profile (Marketer Store) — Tabbed Interface Plan

**Goal:** Replace the stacked sections on the marketer public profile page with Boutiqaat-style tabs, giving buyers a clear, filterable view of 4 distinct product categories.

---

## Current State

| API field            | Condition                                      | Tab target        |
|----------------------|------------------------------------------------|-------------------|
| `own_listings`       | `invitation_id IS NULL`                        | Tab 1             |
| `campaign_listings`  | `invitation_id NOT NULL`                       | Tabs 2 & 3        |
| `classified_listings`| ClassifiedListing records                      | Tab 4             |

`campaign_listings` currently mixes **vendor campaigns** and **marketer-to-marketer campaigns** in a single array. We need to split them.

---

## Required Tabs

| # | Tab key               | Arabic label                    | Data source                                                    |
|---|----------------------|---------------------------------|----------------------------------------------------------------|
| 1 | `own`                | منتجاتي                          | `own_listings` (whereNull invitation_id)                        |
| 2 | `vendor_campaigns`   | منتجات التجار المُروَّج لها      | campaign_listings where `campaign.vendor_id IS NOT NULL`        |
| 3 | `marketer_campaigns` | منتجات المشاهير الآخرين          | campaign_listings where `campaign.vendor_id IS NULL` (owner = marketer) |
| 4 | `classifieds`        | عقارات وسيارات                   | `classified_listings`                                           |

---

## Phase 1: Backend (Task 1)

**File:** `backend/app/Http/Controllers/Api/Public/MarketerProfileController.php`

### Changes to `show()` endpoint:
- Accept new query param `marketer_campaign_page`
- Pass it to `buildListings()`
- Return `marketer_campaign_listings` in the response shape

### Changes to `buildListings()`:
Split the current single campaign query into two:

```php
// Vendor campaign listings (invitation.campaign.vendor_id IS NOT NULL)
$vendorCampaignQuery = MarketerListing::query()
    ->where('marketer_id', $marketerId)
    ->where('country_id', $country->id)
    ->where('status', 'active')
    ->whereNotNull('invitation_id')
    ->whereHas('invitation.campaign', fn($q) => $q->whereNotNull('vendor_id'));

// Marketer-to-marketer campaign listings (invitation.campaign.vendor_id IS NULL)
$marketerCampaignQuery = MarketerListing::query()
    ->where('marketer_id', $marketerId)
    ->where('country_id', $country->id)
    ->where('status', 'active')
    ->whereNotNull('invitation_id')
    ->whereHas('invitation.campaign', fn($q) => $q->whereNull('vendor_id'));
```

### Response shape (new):
```json
{
  "own_listings":              { "items": [...], "meta": {...} },
  "vendor_campaign_listings":  { "items": [...], "meta": {...} },
  "marketer_campaign_listings":{ "items": [...], "meta": {...} },
  "classified_listings":       [...],
  "classified_count":          0
}
```

> **Backward compat:** Keep `campaign_listings` as an alias = `vendor_campaign_listings` for any existing consumers.

---

## Phase 2: Frontend Types (Task 2)

**File:** `frontend/src/features/noon/marketer-profile/helpers/types.ts`

- Add `vendor_campaign_listings` and `marketer_campaign_listings` to `MarketerProfileData`
- Keep `campaign_listings` as optional fallback

---

## Phase 3: API Client (Task 3)

**File:** `frontend/src/features/noon/marketer-profile/api.ts`

- Add `marketerCampaignPage` param to `MarketerListingsParams`
- Pass it in querystring as `marketer_campaign_page`
- Return `marketer_campaign_listings` from response

---

## Phase 4: Tabs UI Component (Task 4)

**New file:** `frontend/src/features/noon/marketer-profile/components/marketer-profile-tabs.tsx`

- Client component with `useState` for active tab
- Tab bar inspired by Boutiqaat: horizontal pill/underline tabs
- Each tab shows a count badge
- Tabs only render if they have data (count > 0), always show Tab 1 (own)
- On tab change, the relevant `MarketerListingsGrid` or classifieds grid renders

---

## Phase 5: Wire into Main View (Task 5)

**File:** `frontend/src/features/noon/marketer-profile/index.tsx`

- Replace the 3 stacked `<section>` blocks with `<MarketerProfileTabs>`
- Pass all listing data and counts as props

---

## Phase 6: i18n Strings (Task 6)

Add/update keys in `messages/en.json` and `messages/ar.json` under `marketerProfile`:
- `tabOwn`, `tabVendorCampaigns`, `tabMarketerCampaigns`, `tabClassifieds`

---

## Execution Order

1. Backend controller split
2. Frontend types update
3. API client update
4. Build `MarketerProfileTabs` component
5. Update `MarketerListingsGrid` to accept `marketer_campaign` section
6. Update `index.tsx`
7. Add i18n strings
8. Git commit each task
