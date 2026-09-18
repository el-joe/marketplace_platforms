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

**Status:** Resolved — see `docs/plans/international_product_shipping.md` (design decision #3
and the adopted Q1 answer) and its Phase 3 implementation.

**Resolution:** "International" is defined as: the cart line's fulfilment listing's `country_id`
(the vendor or admin listing's own storefront country — `App\Services\Checkout\CartLineSource
::originCountryId()`) differs from the order's destination country (`Country::$id` resolved from
the request). This is option 2 in the list above (fulfillment/shipping origin country differs
from the customer's/order's country), computed the same way `SubOrder::isInternational()`
(added in Phase 1) defines it for a placed order.

The policy adopted is **not** an exemption from the COD limit — it's a harder rule: COD is
rejected outright for a cart containing any international line (cross-border COD collection is
operationally unreliable). Implemented in
`App\Services\Customer\CodValidationService::validate(array $cartItems, ?string
$destinationCountryId = null)` — when a destination country is passed and any line resolves as
international via `CartLineSource::isInternational()`, validation fails with
`common.exceptions.checkout.cod_international_not_allowed` before the existing global/Super Mall
limit checks run. Wired into both `CheckoutController::prepare()` and `CheckoutController::
placeOrder()`, which now pass `$country->id` as the second argument.
