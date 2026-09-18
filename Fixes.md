# Senior Fullstack Audit & Fix — MarketPlace Platform
**Role:** You are a senior fullstack developer and problem-solving expert.  
**Mission:** Audit the live codebase, produce a structured fix plan as `FIXES.md`, then execute each fix as an independent sub-agent task.

---

## Repository

```
Monorepo: https://github.com/el-joe/marketplace_platforms
Backend:  /backend  (Laravel 11, PHP 8.3)
Frontend: /frontend (Next.js 15, TypeScript, TailwindCSS)
Commit:   87c58da
```

---

## Platform invariants — read before touching any file

1. **All money = BIGINT base-currency integers.** No `/100` or `*100` anywhere in controllers, services, resources, or TSX — only legitimate `/100` is percentage math (VAT, commission rate, coupon %).
2. **All PKs = UUIDs** via `HasUuids` trait — never `$table->id()`.
3. `quantity_available` and `quantity_remaining` are **VIRTUAL GENERATED** columns — never write to them, never put in `$fillable`.
4. `InventoryMovement` is **append-only** — throws on update/delete.
5. `paid_ad_slots` page-block slots use `pageBlock()` not `block()` on `SliderSlide`/`AdImageItem`.
6. `marketer_campaign_conversions` is the conversion table (not `marketer_conversions`).
7. `flash_sale_vendor_invititions` has intentional double-"ti" typo — preserve everywhere.
8. No JSON blobs for structured relational data — always use proper FK tables.
9. Multi-currency: always show per-currency rows, never sum across currencies.

---

## Step 0 — Pull latest & read the codebase

```bash
cd /repo && git fetch origin && git reset --hard origin/main
git log -1 --format='%h %ci %s'
```

Then read these files **in order** before doing anything else:

```bash
# Backend — core order/checkout
cat backend/app/Http/Controllers/Customer/CheckoutController.php
cat backend/app/Services/CheckoutCalculationService.php
cat backend/app/Services/ShippingSubsidyService.php
cat backend/app/Services/Ads/PlacementAdService.php
cat backend/app/Services/Customer/SponsoredProductService.php

# Backend — marketer/influencer/broker
cat backend/app/Services/MarketerCampaignService.php
cat backend/app/Http/Controllers/Admin/MarketerController.php
cat backend/app/Models/MarketerProfile.php

# Backend — coupon & payments
cat backend/app/Http/Controllers/Customer/CheckoutController.php | grep -n "coupon\|wallet\|gateway" | head -40

# Frontend — checkout
cat frontend/src/features/noon/checkout/helpers/use-checkout.ts
cat frontend/src/features/noon/checkout/payment-summary.tsx
cat frontend/src/features/noon/checkout/types/checkout.type.ts

# Routes (for gap detection)
grep -n "Route::" backend/routes/admin.php | grep -i "cod.*limit\|size.guide\|travel.*filter\|supermal\|omani" | head -20
grep -n "Route::" backend/routes/api_customer_v1.php | grep -i "travel\|special.*request\|ad.*package" | head -20

# Schema — tables that may be missing
python3 -c "
import re
sql = open('backend/database/schema/mysql-schema.sql').read()
for t in ['cod_limit_settings','ad_package_subscriptions','marketer_sample_sizes','travel_search_filters']:
    found = bool(re.search(r'CREATE TABLE \`%s\`' % t, sql))
    print(t, '✅' if found else '❌ MISSING')
"
```

---

## Step 1 — Diagnose: produce `FIXES.md`

After reading the codebase, produce a file called `FIXES.md` at the repo root. Structure it exactly like this:

```markdown
# FIXES.md — Platform Audit Results
Generated: {timestamp} | Commit: {hash}

## CRITICAL (breaks functionality)
### FIX-C1: {title}
- **File(s):** ...
- **Root cause:** ...
- **Fix:** ...
- **Acceptance test:** ...

## HIGH (wrong output, financial impact, or broken UX)
### FIX-H1: {title}
...

## MEDIUM (missing feature that has DB schema but no UI/route)
### FIX-M1: {title}
...

## LOW (cosmetic, locale keys, labels)
### FIX-L1: {title}
...

## SKIP (needs business decision before code — document, do not implement)
### FIX-S1: {title}
- **Reason:** ...
- **Open question:** ...
```

---

## Step 2 — The specific gaps to investigate (from product owner brief)

Investigate each item below. For each: check if it exists, where it's broken, and what the minimal correct fix is.

### A. Nawi Ad Packages — listing boost & popup logic

1. `ad_packages` table and `PartnerAdSubscriptionController` exist. Does the **sort/ranking** actually apply to browse results? Check `BrowseService` and `ProductQueryService` — is there a `WHERE listing boosted = true ORDER BY boost_priority` anywhere?
2. Does the `serious_featured` tier actually trigger a popup? Check if `popup_ad` or `featured_popup` is returned in any API response, and if the frontend has a popup component for it.
3. If the boost ordering and popup are not wired: plan the fix.

### B. COD Limits — not implemented at all

The product owner requires:
- Admin sets a **global max COD cart value** (e.g. 500 OMR)
- **Exceptions** (no limit): Nawi own products, international products
- **Separate limit** for Supermal products

Check:
- Is there a `cod_limit_settings` table? (Likely: NO)
- Is there any COD amount check in `CheckoutController`?
- Plan: migration + admin UI + validation in checkout

### C. Travel Search Filters — backend done, frontend missing

`TravelController` accepts `departure_from`, `departure_to`, `country_id`, `city_id`.  
Check `frontend/src/features/noon/` for a travel browse page. Do the filter inputs exist? If not, plan the frontend component.

### D. Influencer sample sizes — visible in samples workflow

`marketer_profiles` has `clothing_size`, `shirt_size`, `pants_size`, `dress_size`, `abaya_size`, `shoe_size`, `chest_cm`, `waist_cm`, etc.  
Check `partner/marketer_campaigns/show.blade.php` and `admin/marketer_campaigns/show.blade.php`:
- When the admin/vendor dispatches a sample, are the influencer's sizes displayed?
- If not: plan a read-only "Influencer Sizes" card in the sample dispatch UI.

### E. Omani Rial (OMR) currency symbol

The product owner requests the official symbol **ر.ع** (not the ISO code OMR) displayed on invoices and the UI.  
Check:
- `get-currency-symbol.ts` or equivalent in the frontend
- `CurrencyController` in admin — does it support uploading a symbol image for OMR?
- Plan: add `OMR → ر.ع` to the frontend symbol map.

### F. Coupon `shipping_type_restriction` — is it enforced?

`coupons.shipping_type_restriction` enum (`all`/`fbn`/`fbp`/`fbm`) exists.  
Check `CouponService::validate()` or wherever coupons are applied in `CheckoutCalculationService`. Is the restriction actually checked against the cart's listings' `fulfillment_model`? If the field exists but isn't validated: plan the enforcement.

### G. Sponsored products — empty category shows wrong results

`SponsoredProductService::fetchSponsored()` has no category filter.  
Check if `PROMPT-sponsored-category-filter.md` was applied (look for `$categoryIds` param in `fetchSponsored()`).  
If not applied: this is HIGH priority — implement the fix.

### H. Payment summary missing line items

Check `frontend/src/features/noon/checkout/payment-summary.tsx` for these line items:
- `cod_fee` — shown? 
- `warranty_total` — shown?
- `gift_card_applied` — shown?
- `loyalty_discount` — shown?

Report what's missing and plan the fix if needed.

### I. `order.completed` never set automatically

Check `backend/app/Jobs/AutoCompleteOrdersJob.php` — does it exist and is it registered in `routes/console.php`?  
Check `backend/app/Jobs/CheckSlaBreachJob.php` — same.  
If both exist and are registered: mark as DONE. If not: plan.

### J. Marketer checkout crash (B1)

Check `CheckoutController` around line 637 for `resolveMarketerCartItems` or the `groupBy` that would null-pointer on `$item->vendorListing->vendor_id` when `marketer_listing_id` is set.  
Confirm fix is applied or still broken.

---

## Step 3 — Execute fixes as sub-agents

After writing `FIXES.md`, execute each fix in priority order:

**For each fix in CRITICAL then HIGH:**
1. Read the exact files mentioned in `FIXES.md`
2. Write the minimal correct change
3. Run `php -l {file}` for PHP files; `npx tsc --noEmit 2>&1 | grep {filename}` for TS files
4. Confirm no `/100` money bugs introduced
5. Confirm no BIGINT→float conversions
6. Mark the fix as ✅ DONE in `FIXES.md`

**Rules for sub-agent execution:**
- One fix at a time — do not batch
- If a fix requires a DB migration: write the migration file, do NOT run it (server can't be accessed here)
- If a fix requires a business decision (SKIP category): write a `QUESTION-{N}.md` file with the exact question, stop, and move to the next fix
- If a fix has a dependency on another fix: note it and do the dependency first
- Never modify: `database/schema/mysql-schema.sql` (auto-generated), `package-lock.json`, `.env` files

---

## Step 4 — Final report

After all fixes, produce a summary section at the bottom of `FIXES.md`:

```markdown
## Execution Summary
| Fix ID | Status | Files changed | Notes |
|--------|--------|---------------|-------|
| FIX-C1 | ✅ DONE | app/...php | ... |
| FIX-H1 | ⏳ SKIP | — | Needs Q1 answered |
...

## Migrations needed (run on server)
- `php artisan migrate` after applying: {list migration files}

## Questions requiring product owner input
See: QUESTION-1.md, QUESTION-2.md, ...
```

---

## Non-negotiable constraints

- Read before writing — never assume schema, enum values, or route names
- After every file write: re-read it to confirm the change is correct
- No placeholder comments like `// TODO implement`  — either implement it or put it in SKIP
- Locale keys: always add to BOTH `lang/en/` and `lang/ar/` simultaneously
- Migration timestamps: use format `YYYY_MM_DD_HHMMSS_description.php` — check the last migration file for the correct date prefix
- `php artisan schema:dump --prune` must be noted as required after migrations run on server