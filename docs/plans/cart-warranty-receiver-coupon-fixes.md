# Fix Plan: Cart Warranty, Checkout Receivers, Coupon Discount

Date: 2026-09-18
Owner: dev.youssefmagdy@gmail.com

This plan covers three independent, unrelated bugs. Each has its own root-cause
analysis and a self-contained sub-agent prompt so the three fixes can be worked
(and reviewed) in parallel by separate sub-agents. Run each "Sub-agent prompt"
block below verbatim as a task for a sub-agent (e.g. via the `Agent` tool or a
fresh Claude Code session). Do the phases in order within each issue; issues
themselves have no ordering dependency.

---

## Issue 1 — "Cart item not found" when adding warranty to an existing cart item

### Root cause (confirmed via code read)

- Route: `backend/routes/api_customer_v1.php:311` → `CartController::updateItemWarranty`
  (`backend/app/Http/Controllers/Customer/CartController.php:373-432`).
- Lookup: `$cart = $this->resolveCart($request); $item = $cart->items()->find($id);`
  (lines 379-385). If not found → 404 `cart.item_not_found`.
- `CartService::getOrCreateCart()` (`backend/app/Services/Customer/CartService.php:68-85`)
  resolves the cart via `Cart::firstOrCreate(['user_id' => $customer->id, 'country_id' => $countryId], ...)`.
  Carts are scoped **per country**. If the `{country}` segment on the warranty PATCH
  request differs from the country the item was actually added under, `resolveCart()`
  returns/creates a *different, empty* cart — the item genuinely isn't in it — producing
  "Cart item not found" even though the product is visibly in the user's cart in the UI.
- `CartService::getOrCreateGuestCart()` (lines 87-111) has the opposite inconsistency:
  it looks the guest cart up **only by `session_token`**, ignoring `country_id` in the
  `WHERE` clause (only used when creating a brand-new cart). So guest vs. authed
  behavior around country-scoping is already inconsistent.
- Secondary/compounding frontend bug: `frontend/src/features/noon/productView/added-to-cart-sheet.tsx:156-164`
  calls `updateItemWarranty({ cartItemId: isSheetOpen.cartItemId || "", ... })`. If
  `cart_item_id` is ever missing from the add-to-cart response, this silently sends an
  **empty id**, guaranteeing a 404. There's also a leftover `console.log("isSheetOpen.cartItemId", ...)`
  around line 157 suggesting this was already being chased.
- There is **no server-side concept of a "pending" warranty** selected before a product
  exists in the cart. `addItem()` (`CartController.php:269-316`) accepts `warranty_plan_id`
  in the *same* `POST /cart/items` call and sets it directly on the new `CartItem`
  (lines 290-296) **without** the applicability check that `updateItemWarranty()` performs
  via `warrantyPlanService->getPlansForProduct()` (lines 398-410). The frontend approximates
  "select warranty before add to cart" purely in memory via `warranty-selection-context.tsx`
  (`selectedPlanId`), submitted together with the `addItem` call from `cart-button.tsx:44-56`.
  This satisfies "if I select warranty without product, keep it selected until I add the
  product" today *only* for the PDP-select-then-add-to-cart flow — it does not persist
  across reloads and does not cover "product already in cart, then add warranty" which is
  the reported failure.

### Required behavior (per issue report)

1. Adding a product to the cart and adding a warranty to that cart item must be two
   independent actions — updating warranty on an item that is already in the cart must
   work reliably.
2. If a warranty is selected on the PDP before the product is added to cart, it must stay
   selected/pending and get attached automatically once the product is added.

### Sub-agent prompt — Issue 1

```
You are fixing a bug in the marketplace repo at /var/www/marketplace (Laravel backend
in backend/, Next.js frontend in frontend/). Read docs/plans/cart-warranty-receiver-coupon-fixes.md
section "Issue 1" first for full root-cause context before touching anything — do not
re-derive it from scratch.

Bug: PATCH /api/customer/v1/{country}/cart/items/{cartItemId}/warranty returns
{"success":false,"message":"Cart item not found."} even when the product is already
visibly in the user's cart. Root cause is that CartController::resolveCart()
(backend/app/Http/Controllers/Customer/CartController.php:40-65) resolves carts scoped
by (user_id or session_token) + country_id (backend/app/Services/Customer/CartService.php:68-111),
so a request whose {country} route segment doesn't match the cart the item was added
under resolves to a different/empty cart and the item lookup at CartController.php:381
legitimately fails to find it there.

Do the following, in order:

1. Confirm the exact reproduction: add a product to cart under country A, then send the
   warranty PATCH under a URL with a different {country} segment (or after country context
   changed client-side), and confirm this is what the frontend does today (check
   frontend/src/features/noon/cart/api/cart-warranty.actions.ts and
   frontend/src/services/cart.ts for how the {country} path param is sourced relative to
   how the add-to-cart call sources it). Also separately confirm/rule out the
   isSheetOpen.cartItemId || "" empty-id fallback in
   frontend/src/features/noon/productView/added-to-cart-sheet.tsx:156-164 as a contributing
   cause (remove the stray console.log at line ~157 either way, and replace the `|| ""`
   fallback with a guard that no-ops / surfaces an error instead of firing a request with
   an empty id).

2. Fix cart resolution so a warranty update (and any other single-item cart mutation)
   is resolved against the cart that actually owns cart_item_id, not re-derived from the
   country_id) is resolved against the cart that actually owns cart_item_id, not re-derived
   solely from country_id in the current request. Preferred approach: in
   CartController::updateItemWarranty (and audit sibling item-level actions:
   updateItemQuantity, removeItem, etc. in the same controller for the same bug), look the
   CartItem up first by its own primary key with an ownership check (cart belongs to this
   user_id / session_token), rather than requiring the resolved "current" cart to already
   contain it. Do not weaken authorization — a user must still only be able to mutate their
   own cart items (verify via cart.user_id === current customer id, or cart.session_token
   === current guest token, whichever applies), just don't require the country_id on the
   resolved "current" cart to match.

3. Align CartService::getOrCreateCart() and getOrCreateGuestCart() so country scoping is
   applied consistently between the authed and guest code paths (currently guest lookup at
   CartService.php:87-111 ignores country_id in its WHERE clause while the authed path
   at lines 68-85 enforces it). Decide and document the single correct rule, then make both
   paths follow it.

4. Add the "pending warranty persists until product is added" behavior properly: when a
   customer selects a warranty plan on the PDP before clicking "Add to cart"
   (frontend/src/features/noon/productView/warranty-selection-context.tsx), that selection
   should already be submitted together with the add-to-cart call
   (frontend/src/features/noon/productView/cart-button.tsx:44-56) — verify this still works
   end to end after your changes, including that CartController::addItem (lines 269-316)
   validates the warranty plan's applicability to the product the same way
   updateItemWarranty does (lines 398-410), since addItem currently skips that check. Add
   the missing validation to addItem so both entry points enforce the same rule.

5. Write/update backend feature tests covering: (a) add product to cart, then PATCH
   warranty onto that cart item as a separate call — must succeed; (b) select warranty
   plan_id on add-to-cart request directly — must succeed and validate applicability;
   (c) attempting to attach a warranty plan not applicable to the product — must return a
   clear validation error, from both endpoints; (d) a guest cart and an authed cart both
   resolve item-level actions correctly regardless of the country segment in the URL only
   diverging from a case-appropriate scenario (do not fabricate a scenario where country
   *should* fail — clarify only genuine cross-cart-ownership attempts are rejected).

6. Run the relevant PHPUnit/Pest suite for cart + warranty and the frontend lint/typecheck
   for the touched files. Report back exactly which files changed and why, and paste the
   test run output.

Do not touch the receiver/address code or the coupon discount code — those are separate
issues handled by other sub-agents in parallel.
```

---

## Issue 2 — New checkout address should also create a matching receiver

### Root cause (confirmed via code read)

- `Address` (`addresses` table, `marketplace_platform.sql:139-162`) and `CustomerReceiver`
  (`customer_receivers` table, `marketplace_platform.sql:22022-22031`) are two entirely
  separate models with no relationship between them.
- `AddressController::store()` (`backend/app/Http/Controllers/Customer/AddressController.php`,
  ~lines 30-46) only ever writes to `addresses` — it never creates, updates, or references
  a `CustomerReceiver` row.
- `CustomerReceiver` CRUD exists as its own subsystem: `ReceiverController.php` (lines 17-84)
  + `ReceiverService.php` (lines 11-21) — fully functional but currently orphaned from the
  address flow.
- `CheckoutController::resolveReceiver()` (lines 1875-1884) resolves a `CustomerReceiver`
  by `receiver_id` if given, else falls back to the customer's default `CustomerReceiver`.
  `buildAddressSnapshot()` (lines 1886-1899) falls back further to the *address's own*
  `recipient_name` / `recipient_phone` if no receiver exists at all.
- Frontend: `frontend/src/features/noon/checkout/order-receiver-card.tsx` reads from the
  **addresses** list (`useQuery(["addresses"], getAddresses)`), not from `/receivers` at
  all, and its "Change Receiver" button has **no `onClick` handler** — it's dead. `receiver_id`
  is declared in the place-order payload type
  (`frontend/src/features/noon/checkout/types/place-order.type.ts:51`) but nothing in the
  checkout feature ever assigns it a value.
- Net effect: a customer who only ever adds addresses through the checkout/address dialog
  (`frontend/src/components/shared/dialogs/address-dialog/address-dialog.tsx`) will have
  **zero** `CustomerReceiver` rows, ever, and the UI can never select or change one.

### Required behavior (per issue report)

When a customer adds a new address during checkout, a receiver record should be created
automatically for that address using the same name and address/phone info.

### Sub-agent prompt — Issue 2

```
You are fixing a bug in the marketplace repo at /var/www/marketplace (Laravel backend in
backend/, Next.js frontend in frontend/). Read docs/plans/cart-warranty-receiver-coupon-fixes.md
section "Issue 2" first for full root-cause context — do not re-derive it from scratch.

Context: addresses (addresses table) and receivers (customer_receivers table, model
CustomerReceiver) are separate, unrelated entities today. AddressController::store()
(backend/app/Http/Controllers/Customer/AddressController.php) only writes to addresses and
never touches customer_receivers. ReceiverController.php /
backend/app/Services/Customer/ReceiverService.php already implement full receiver CRUD
(index/store/update/destroy/setDefault) — reuse that logic, don't reinvent it. Checkout's
resolveReceiver()/buildAddressSnapshot() in
backend/app/Http/Controllers/Customer/CheckoutController.php (~lines 1875-1899) already
know how to consume a CustomerReceiver.

Required behavior: when a customer adds a new address (whether from the checkout page's
address dialog or the general address management screen — check both entry points), a
matching CustomerReceiver record must also be created automatically, using the same
recipient name and phone as the address, so it becomes selectable during checkout.

Do the following:

1. Decide and implement the backend link: in AddressController::store() (and
   update(), if editing recipient_name/recipient_phone on an address should keep its
   linked receiver in sync — use your judgment on whether to also update or leave existing
   receivers alone, favoring not silently mutating a receiver the customer may have since
   customized), create a CustomerReceiver for the customer using the address's
   recipient_name/recipient_phone (via ReceiverService, not ad hoc), unless a receiver
   with the same name+phone already exists for that customer (avoid duplicate receiver
   spam — check backend/app/Models/CustomerReceiver.php and customer_receivers schema
   for what uniqueness makes sense, e.g. same customer_id + name + phone). If this is the
   customer's first receiver, mark it as_default consistent with how ReceiverService/
   ReceiverController already assign defaults.

2. Fix the checkout frontend to actually use receivers instead of silently reading
   addresses: frontend/src/features/noon/checkout/order-receiver-card.tsx currently queries
   ["addresses"] and displays defaultAddress?.recipient_name/recipient_phone, with a
   "Change Receiver" button with no onClick. Wire it to fetch the customer's receivers
   (find or add the relevant API client function alongside the existing address API calls,
   pointing at the /receivers endpoints already implemented by ReceiverController), display
   the default receiver, implement "Change Receiver" to open a selection UI (reuse existing
   dialog patterns in frontend/src/components/shared/dialogs if there's a suitable one, e.g.
   the address-dialog's structure, for consistency), and make sure the selected receiver_id
   is actually included in the place-order payload
   (frontend/src/features/noon/checkout/api/post.ts and
   frontend/src/features/noon/checkout/types/place-order.type.ts already declare
   receiver_id — currently nothing ever sets it).

3. Make sure creating an address from checkout (not just the standalone address management
   page, if they're different code paths) also creates/links a receiver per step 1 — trace
   frontend/src/components/shared/dialogs/address-dialog/address-dialog.tsx's submit path
   to confirm it hits the same AddressController::store() endpoint; if there's a second
   address-creation endpoint specific to checkout, apply the same fix there.

4. Add backend tests: creating an address creates exactly one matching receiver (and not a
   duplicate on a second identical address); creating a second, differently-named address
   does not touch the first receiver's default flag incorrectly; checkout can pass
   receiver_id and CheckoutController::resolveReceiver() picks it up correctly end to end.

5. Manually verify in a browser: add a new address during checkout, confirm a receiver
   with the same name/phone now appears as selectable, and that placing the order records
   the chosen receiver on the resulting order snapshot (buildAddressSnapshot()).

6. Report back exactly which files changed and why, and paste relevant test output.

Do not touch the cart/warranty code or the coupon discount code — those are separate
issues handled by other sub-agents in parallel.
```

---

## Issue 3 — Coupon applied but discount shows 0

### Root cause (confirmed via code read)

Pipeline: `CartController::applyCoupon` → `CartService::applyCoupon` →
`CartService::recalculateCart` → `CheckoutCalculationService::applyCoupon` →
`CheckoutPricingEngine::applyCoupon`.

1. **Guest carts never get a discount.** `CartService::recalculateCart()`
   (`backend/app/Services/Customer/CartService.php:519-530`):
   ```php
   $discount = 0;
   if ($cart->coupon && $cart->customer) {
       $result = $this->calculationService->applyCoupon(...);
       $discount = $result['error'] ? 0 : $result['discount'];
   }
   ```
   `Cart::customer()` (`backend/app/Models/Cart.php:38-40`) is `belongsTo(Customer::class, 'user_id')`.
   For guest carts `user_id` is `NULL`, so `$cart->customer` is always `null` and the `if`
   guard is false — discount stays 0 even though `cart.coupon_id` is set. Separately,
   `CartService::applyCoupon()` type-hints a non-nullable `Customer $customer` and is called
   with `auth('customer')->user()`, which is `null` for guests — this can throw a `TypeError`
   for guests rather than degrading gracefully.

2. **Stricter re-validation on every recalculation can silently zero an already-"applied"
   coupon for logged-in customers**, which best matches the client's report (discount is 0
   right after applying, not "coupon rejected"). `CartService::applyCoupon()` only checks
   active/date range, total usage limit, per-customer usage, and `min_order_amount`
   (lines 415-438) before setting `cart.coupon_id`. But every subsequent
   `recalculateCart()` call re-validates through
   `CheckoutPricingEngine::validateCouponEligibility()`
   (`backend/app/Services/Checkout/CheckoutPricingEngine.php:721-812`), which additionally
   checks `country_ids`, `currency` match, `customer_eligibility`
   (`new_customers`/`specific_users`/`specific_segment`), `shipping_type_restriction`, and
   usage via `CouponUsage` records. If any of these fail, `applyCoupon()` returns
   `discount: 0` with an error string, and `recalculateCart()` (line 530) sets
   `$discount = 0` **without detaching the coupon or surfacing the error** — the cart still
   shows "coupon applied" (`coupon_id` still set) but `discount` renders as 0. This exactly
   matches the client note "I applied a coupon and the discount still equals 0."

3. `recalculateCart()`'s call into `calculationService->applyCoupon()` never passes a
   `countryId`, so the `country_ids` eligibility check is effectively skipped on every
   recalculation regardless — a related consistency gap, not itself the zeroing cause.

4. Sample-data caveat for QA: every coupon row currently in `marketplace_platform.sql`
   has `is_active = 0` — rule this out first when reproducing locally (flip `is_active` or
   seed a fresh active coupon) before chasing the code bug.

5. The discount math itself (`CheckoutPricingEngine::applyCoupon()` lines 117-127,
   percentage vs `fixed_amount`, `max_discount` cap, `min` against subtotal) looks correct
   against this codebase's integer-money convention — not the source of the bug.

### Sub-agent prompt — Issue 3

```
You are fixing a bug in the marketplace repo at /var/www/marketplace (Laravel backend in
backend/). Read docs/plans/cart-warranty-receiver-coupon-fixes.md section "Issue 3" first
for full root-cause context before touching anything — do not re-derive it from scratch.

Bug: a customer applies a coupon code to their cart; the API reports it as applied but the
cart summary's discount amount is 0 (endpoint chain: CartController::applyCoupon ->
CartService::applyCoupon -> CartService::recalculateCart ->
CheckoutCalculationService::applyCoupon -> CheckoutPricingEngine::applyCoupon). Money values
in this codebase are base-currency integers (see memory: /100 is percent math, not a bug) —
do not "fix" correct integer math, this bug is a control-flow/validation-mismatch bug, not
an arithmetic one.

Two concrete root causes to fix:

1. Guest cart discount is never computed. CartService::recalculateCart()
   (backend/app/Services/Customer/CartService.php:519-530) only computes a discount when
   `$cart->coupon && $cart->customer` is true. Cart::customer()
   (backend/app/Models/Cart.php:38-40) is belongsTo(Customer::class, 'user_id'), which is
   always null for guest carts (identified by session_token instead of user_id — see carts
   schema). Decide the correct product behavior (check whether coupons should be usable by
   guests at all per business rules elsewhere in the codebase — e.g. usage_limit_per_customer,
   customer_eligibility enum values like 'new_customers'/'specific_users' imply coupons are
   customer-identity-aware) and implement one of: (a) make the discount calculation path
   guest-safe by passing null/guest-context through to CheckoutCalculationService/
   CheckoutPricingEngine and having eligibility checks explicitly handle "no customer"
   (e.g. reject customer_eligibility != 'all' for guests with a clear error, but still allow
   'all'-eligibility coupons to discount), or (b) explicitly disallow applying a coupon to a
   guest cart in CartController::applyCoupon with a clear 4xx error message instead of
   silently accepting it and showing 0. Also fix CartService::applyCoupon()'s
   non-nullable `Customer $customer` type hint / call site (auth('customer')->user() is null
   for guests) so this doesn't throw an uncaught TypeError for guests in the meantime.

2. A coupon that CartService::applyCoupon() (lines 413-444) accepted and attached to
   cart.coupon_id can later fail the *stricter* re-validation inside
   CheckoutPricingEngine::validateCouponEligibility()
   (backend/app/Services/Checkout/CheckoutPricingEngine.php:721-812, checks country_ids,
   currency, customer_eligibility, shipping_type_restriction, CouponUsage-based limits) every
   time CartService::recalculateCart() runs (e.g. on cart view/update), and when that
   happens recalculateCart() (CartService.php:519-530) just sets discount = 0 silently — the
   coupon still looks "applied" (coupon_id unchanged) but never discounts anything, with no
   error surfaced to the customer. Fix this by making the two validation paths consistent
   and by making failure explicit: when recalculateCart() gets result['error'] from
   applyCoupon(), it must not leave the cart in a state that looks successfully-applied.
   Preferred fix: extract the full eligibility check (currently split between
   CartService::applyCoupon()'s narrower checks and
   CheckoutPricingEngine::validateCouponEligibility()'s stricter checks) into one shared
   validation used at apply-time AND recalculation-time, so a coupon that passes at apply
   time cannot later silently fail at recalculation time for reasons apply-time didn't check.
   Where recalculation still legitimately invalidates a coupon (e.g. cart contents changed
   such that min_order_amount is no longer met), have it detach the coupon
   (cart.coupon_id = null) and expose a clear message/flag in the cart response (check
   CartResource.php for a place to add e.g. `coupon_error`) rather than just zeroing discount
   silently.

3. Also pass the resolved countryId into calculationService->applyCoupon() from
   recalculateCart() (currently omitted, CartService.php:519-530) so country_ids eligibility
   is evaluated consistently at recalculation time, matching whatever check (or lack of one)
   is applied at initial CartService::applyCoupon() time — align both to use the same rule
   from your unified validation in step 2.

4. Before testing, note that every coupon row in marketplace_platform.sql has
   is_active = 0 — seed or flip an active test coupon in your local/test DB so you're not
   chasing a false bug caused by inactive sample data.

5. Add backend tests: (a) authed customer applies a valid, eligible, active coupon —
   discount is > 0 and matches expected percentage/fixed calculation; (b) guest cart with
   a coupon behaves per your chosen policy from step 1 (either computes discount correctly
   for eligible coupons, or is cleanly rejected with a clear error — not silently 0); (c) a
   coupon that passes CartService::applyCoupon()'s checks but fails a
   validateCouponEligibility()-only check (e.g. currency mismatch or customer_eligibility)
   is rejected/detached with a surfaced error at apply time, not silently accepted then
   zeroed later; (d) recalculating a cart after cart contents change such that
   min_order_amount is no longer met detaches the coupon and reports why.

6. Run the relevant PHPUnit/Pest suite for cart + coupon. Report back exactly which files
   changed and why, including the product decision you made for guest-coupon handling, and
   paste the test run output.

Do not touch the cart/warranty code or the receiver/address code — those are separate
issues handled by other sub-agents in parallel.
```

---

## Execution notes

- These three sub-agent prompts are independent of each other (different files/tables) and
  can be run **in parallel** as three separate sub-agent tasks.
- Each prompt is self-contained: it restates the relevant root cause so the sub-agent
  doesn't need to re-explore the codebase from scratch, but each sub-agent should still
  verify current code state before editing, since this plan is a snapshot as of 2026-09-18.
- After all three land, do one integration pass: add a product to cart as a guest, add a
  warranty, apply a coupon, add an address, and place an order — to confirm nothing in one
  fix regressed another (they touch overlapping controllers: `CartController`,
  `CheckoutController`).
