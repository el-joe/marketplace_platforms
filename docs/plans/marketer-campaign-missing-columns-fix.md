# Marketer Campaign SQL Error — Missing Columns Fix

**Date:** 2026-09-29  
**URL:** `partner.noon.codefanz.com/marketer-campaigns/create/{listing_id}`  
**Errors:**
- `SQLSTATE[42S22]: Unknown column 'campaign_category' in 'field list'`
- `SQLSTATE[42S22]: Unknown column 'travel_package_id' in 'field list'`

---

## Root Cause

Two migrations were created on 2026-09-29 to backfill missing columns on `marketer_campaigns`:

| Migration | Columns Added |
|-----------|--------------|
| `2026_09_29_120000_add_travel_and_classified_listing_to_marketer_campaigns` | `travel_package_id`, `classified_listing_id` |
| `2026_09_29_140000_add_campaign_category_to_marketer_campaigns` | `campaign_category` |

**Locally**: Both migrations show `Ran` — columns exist, no error.  
**Production** (`partner.noon.codefanz.com`): Migrations were NOT run — columns are absent, INSERT fails.

The `MarketerCampaign` model lists these in `$fillable` and `MarketerCampaignService` always writes them. So every campaign creation fails on production until migrations run.

---

## Fix

### Sub-agent 1 — Run pending migrations on the production/staging server

This is a deployment step. Since we can't SSH to production from here, document the exact command and verify it is safe to run:

1. Read both migrations and confirm they are idempotent (they already use `if (!Schema::hasColumn(...))` guards — confirm this)
2. Run `php artisan migrate --pretend` locally to confirm what would execute
3. Output the exact command that must be run on production:
   ```bash
   cd /var/www/marketplace/backend && php artisan migrate
   ```
4. Confirm no other pending migrations would cause side effects (currently only `2026_09_29_133041_sync_global_system_type_on_vendor_listings` is pending locally — check if it's safe)

---

### Sub-agent 2 — Verify the Partner Portal campaign create flow end-to-end (code audit)

Verify that after migrations run, the full create flow works correctly for a **product** campaign:

1. Read `app/Http/Controllers/Partner/MarketerCampaignController.php` — find the `create` and `store` methods, confirm `campaign_category` is set to `'product'` when creating from a vendor/admin listing
2. Read `app/Services/MarketerCampaignService.php` around lines 185-200 — confirm `campaign_category`, `travel_package_id`, `classified_listing_id` are correctly set from the campaign source DTO
3. Read the partner portal Blade view for campaign creation — confirm the form submits the right fields
4. Run: `php artisan test --filter=MarketerCampaign 2>&1` — report results

---

### Sub-agent 3 — Add a guard/fallback so this never silently fails again

In `MarketerCampaignService` where the campaign is created (around line 190-195), add a check: if `campaign_category` is not being set, default it to `'product'` so old code paths don't break if the column is somehow absent.

Also verify the `MarketerCampaign` model has a default value for `campaign_category` in its `$attributes` array so new instances always have it set:

```php
protected $attributes = [
    'campaign_category' => 'product',
];
```

Check if this already exists. If not, add it.

---

## Files Involved

| File | Action |
|------|--------|
| `database/migrations/2026_09_29_120000_*` | Confirm idempotent, run on production |
| `database/migrations/2026_09_29_140000_*` | Confirm idempotent, run on production |
| `app/Models/MarketerCampaign.php` | Add `$attributes` default for `campaign_category` if missing |
| `app/Services/MarketerCampaignService.php` | Verify correct column assignment |
| `app/Http/Controllers/Partner/MarketerCampaignController.php` | Verify store() sets campaign_category |

---

## Non-issues (already confirmed correct locally)

- Both migrations are idempotent (use `Schema::hasColumn` guards): ✅  
- `MarketerCampaign` model has all 3 columns in `$fillable`: ✅  
- `MarketerCampaignService` sets all 3 columns on create: ✅  
- Local DB has all columns: ✅
