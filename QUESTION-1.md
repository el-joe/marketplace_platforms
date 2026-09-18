# QUESTION-1: Definition of "international product" for COD exemption

**Context:** FIX-S1 in FIXES.md — COD (cash on delivery) limits are implemented and working
(`backend/app/Services/Customer/CodValidationService.php`), including:
- Global max COD cart value (`cod_global_max_amount` setting)
- Separate Supermal-specific limit (`cod_supermall_max_amount`)
- Nawi/platform-own products already exempt (`$item->adminListing !== null` check)

**Missing:** The brief also requires an exemption for "international products", but there is no
corresponding concept anywhere in the schema or models — no `is_international`,
`ships_internationally`, or `international_shipping` field exists on `VendorListing`,
`AdminListing`, `Product`, or `Country` (confirmed via grep across `backend/app` and
`mysql-schema.sql`, zero hits).

**Open question for product owner:** What defines an "international product" for COD-exemption
purposes?
1. Seller's country differs from the storefront's operating country?
2. Fulfillment/shipping origin country differs from customer's country?
3. An explicit per-listing flag set by the vendor/admin (e.g. `is_international_shipping`)?
4. Tied to an existing cross-border shipping method/carrier selection?

**Why this blocks implementation:** Once defined, the code fix itself is small — a one-line
addition to `CodValidationService::validate()`'s exemption check
(`if ($item->adminListing !== null || $this->isInternational($item)) continue;`) — but the
field/logic to determine "international" must be specified first, and may require a new
migration (new column) depending on the answer.

**Status:** Blocked — no code changes made for this item, per instructions for SKIP-category fixes.
