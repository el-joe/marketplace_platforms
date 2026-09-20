# Warehouse type integrity — analysis, scenarios, and sub-agent prompts

## Problem
Vendor-owned warehouses (`owner_vendor_id` NOT NULL) exist with `type = platform_fbn`.
`warehouses.type` is `enum NOT NULL` with no default; with non-strict SQL mode an insert that omits it stores the
first enum member, `platform_fbn`.

## Known creation paths (backend/)
| Path | File | Type set? |
|---|---|---|
| Vendor approval job | app/Jobs/VendorApprovedJob.php (raw DB::table insert, "— Default Warehouse") | **NO → root cause** |
| Vendor onboarding | app/Services/Vendor/OnboardingService.php | seller_owned ✔ |
| Partner/vendor register | app/Services/VendorWarehouseService.php::registerWarehouse | seller_owned ✔ |
| Admin create/update | Admin/WarehouseController + Store/UpdateWarehouseRequest + WarehouseService::create | type & owner_vendor_id independent → **can mismatch** |
| Seeders / tests | PerformanceDatasetSeeder, tests/Support/MarketplaceScenario.php | verify |
| Marketer | no warehouse creation found — confirm |

## Invariant
- `owner_vendor_id IS NOT NULL` ⇒ type ∈ {seller_owned, third_party}, never platform_fbn.
- `type = platform_fbn` ⇒ `owner_vendor_id IS NULL`.
- Enforced at: request validation, service layer (single choke point), model `saving` guard, and data repair migration.

## Scenarios
1. Vendor approved without default warehouse → warehouse is seller_owned, owned by vendor.
2. Vendor approved that already has default_warehouse_id → nothing created.
3. Admin creates platform_fbn with no vendor → OK.
4. Admin creates platform_fbn with a vendor → 422.
5. Admin creates seller_owned without vendor → 422.
6. Admin updates type to platform_fbn on a vendor-owned warehouse → 422.
7. Partner registers warehouse → seller_owned (regression).
8. Onboarding → seller_owned (regression).
9. Model guard: Warehouse::create/DB-level via Eloquent with invalid combo throws.
10. Data repair: existing rows with owner + platform_fbn become seller_owned; platform rows untouched; idempotent; reversible-safe.
11. FBN fee jobs / vendor-limit logic no longer pick up vendor warehouses.

## Sub-agent prompts
### Agent A — Fix creation paths
Fix VendorApprovedJob to set `type => seller_owned` (prefer routing via VendorWarehouseService/Eloquent).
Add the invariant to Store/UpdateWarehouseRequest (withValidator) and a guard in WarehouseService::create/update.
Add a `saving` guard on the Warehouse model. Do not touch unrelated code.

### Agent B — Data repair migration
Write a new migration (latest date prefix) that sets type=seller_owned where owner_vendor_id IS NOT NULL AND type='platform_fbn'.
Idempotent, no-op down(). Do NOT run it.

### Agent C — Tests
Add Pest/PHPUnit tests under backend/tests covering scenarios 1–9 using existing helpers. Run only those tests and report.

### Agent D — Audit (read-only)
Confirm marketer flows create no warehouses; grep seeders/tests/other raw inserts into `warehouses` omitting type;
check FBN fee jobs/queries for reliance on type. Report findings only.
