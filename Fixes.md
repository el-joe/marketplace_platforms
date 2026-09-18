# Fix Plan — Customer/Admin Issues (2026-09-18)

Root-cause investigation completed via code exploration (Laravel backend `backend/`, Next.js
customer frontend `frontend/`, Blade admin panel `backend/resources/views/admin`). Each issue
below is a **self-contained prompt** meant to be run as an independent subagent. Each subagent
must, when finished:
1. Implement the fix (backend + frontend/admin as needed).
2. Manually verify with `php artisan tinker`/relevant routes or a quick build/typecheck.
3. `git add` only the files it touched and create **one commit** for its issue
   (do not amend, do not push). Commit message format: `Fix: <short summary> (FIX-<ID>)`.
4. Report back a short summary of what changed and the commit hash.

Do not work on more than one issue per subagent/session — this avoids merge conflicts since
several issues touch overlapping files (e.g. order detail pages).

---

## FIX-1 — Order summary page has no invoice (`/orders/${orderNumber}`)

**Root cause (frontend-only):** Backend is fully implemented —
`GET /api/customer/v1/{country}/{orderNumber}/invoice` (`backend/routes/api_customer_v1.php:562`)
→ `OrderController::invoice` (`backend/app/Http/Controllers/Api/Customer/OrderController.php:173-184`)
→ returns `OrderInvoiceResource` with full line items/tax/totals. Nothing frontend calls it.
`frontend/src/features/noon/profile/orders/summary/components/invoice-card.tsx` only renders a
static header + a non-interactive `DownloadIcon` (no `onClick`/`href`, lines ~13-35) and, when the
order is in-progress, an "invoice in progress" message — otherwise renders `null`. It never fetches
`/orders/{orderNumber}/invoice` or links to a downloadable/printable invoice.

**Prompt:**
> You are fixing FIX-1 in /var/www/marketplace. The backend endpoint
> `GET /api/customer/v1/{country}/{orderNumber}/invoice` already works
> (`backend/app/Http/Controllers/Api/Customer/OrderController.php:173-184`, returns
> `OrderInvoiceResource`). The bug is purely on the frontend: open
> `frontend/src/features/noon/profile/orders/summary/components/invoice-card.tsx` — it renders a
> disabled-looking download icon with no click handler and returns `null` unless the order is
> in-progress. Fix it so that when the order is NOT in-progress (invoice available), the component
> fetches/links to the invoice endpoint and lets the customer download or open it (PDF/print view —
> check if `OrderInvoiceResource` is meant to back a printable HTML view or if you need to add one;
> if no PDF generation exists, render a printable invoice view page using the JSON from that
> endpoint, e.g. a route like `/orders/[orderNumber]/invoice` that calls window.print(), OR wire a
> `Content-Disposition` PDF response backend-side if that's more consistent with the rest of the
> app — check for existing PDF libs in backend/composer.json first (e.g. barryvdh/laravel-dompdf)
> before adding a new dependency). Wire the download icon's onClick to open/download it. Test by
> hitting the invoice endpoint for a real completed order number and confirming the frontend button
> now works. Commit your changes as one commit: "Fix: add working invoice download on order summary
> page (FIX-1)".

---

## FIX-2 — Cannot book more than 1 seat for a travel package (group booking)

**Root cause (frontend-only):** Backend fully supports multi-seat bookings already:
`CreateBookingRequest` requires `travelers_count` (int, min:1, max:50) —
`backend/app/Http/Requests/Customer/Travel/CreateBookingRequest.php:16-19`;
`TravelBookingService::book()` prices and stores it
(`backend/app/Services/Customer/TravelBookingService.php:60-82`); route
`POST listings/travel/{slug}/bookings` (`backend/routes/api_customer_v1.php:124-126`). But there is
**no frontend caller of this endpoint at all**. The package detail page
(`frontend/src/features/flights/package-details/components/booking-sidebar.tsx`) only shows price/
seats-remaining and opens a `ContactModal` that submits a name/email/phone "inquiry" via
`submitTravelInquiry()` — not a real booking, and has no traveler-count field.
`frontend/src/features/flights/api/bookings.actions.ts` only has list/detail/cancel/passport-upload
actions — no `createBooking`. `PricingTiers` component only displays tiers read-only.

**Prompt:**
> You are fixing FIX-2 in /var/www/marketplace. The backend already supports booking a travel
> package with any number of travelers via `POST listings/travel/{slug}/bookings` with a
> `travelers_count` field (1-50) — see
> `backend/app/Http/Requests/Customer/Travel/CreateBookingRequest.php` and
> `backend/app/Services/Customer/TravelBookingService.php::book()`. The frontend has NO booking
> flow at all yet — `frontend/src/features/flights/package-details/components/booking-sidebar.tsx`
> only opens a `ContactModal` that submits an inquiry (see
> `frontend/src/features/flights/package-details/components/contact-modal.tsx` and
> `travel-packages.actions.ts`'s `submitTravelInquiry`), not an actual booking. Build the missing
> piece: add a `createBooking` server action in
> `frontend/src/features/flights/api/bookings.actions.ts` that POSTs to
> `listings/travel/{slug}/bookings` with `travelers_count` (and any other required fields the
> request class needs — check `CreateBookingRequest` for the full field list, e.g. traveler names/
> passport info if required). Add a traveler-count stepper/input to the booking sidebar (respecting
> `available_seats`/`seatsRemaining()` from `TravelPackage`, don't let the user request more seats
> than remain), show the computed total price using the existing group-pricing tier logic
> (`priceForTravelersCount`), and wire the submit button to call the new action and redirect to the
> booking confirmation/detail page on success. Keep the existing "Contact us" inquiry option as a
> separate, secondary action if useful, but the primary flow must be a real booking. Test by
> creating a booking for 3+ travelers against a real package and confirming `travelers_count` and
> price are correct in the DB. Commit as one commit: "Fix: add multi-seat travel package booking
> flow to frontend (FIX-2)".

---

## FIX-3 — Admin cannot convert a booking inquiry into a real booking

**Root cause:** This capability exists only for the Travel Agency Portal, never for Admin.
`TravelAgencyPortal\PackageInquiryController::convertToBooking` is wired via
`backend/routes/api_travel_agency.php:72` and `backend/routes/travel.php:115` (behind
`travel_agency.can:inquiries.manage`). The Admin controller
`backend/app/Http/Controllers/Admin/TravelPackageInquiryController.php` (33 lines) only has
`index()` — no convert/approve method — and `backend/routes/admin.php:1611-1613` registers only a
GET listing route. The admin Blade view (`resources/views/admin/travel/inquiries/index.blade.php`)
is read-only. This is a missing feature, not a permissions bug.

**Prompt:**
> You are fixing FIX-3 in /var/www/marketplace. Admins need the ability to convert a travel
> package booking inquiry into a real confirmed booking from the admin panel — this endpoint
> currently only exists for Travel Agency Portal users, not Admin. Study
> `TravelAgencyPortal\PackageInquiryController::convertToBooking` (find it via
> `backend/app/Http/Controllers/Api/TravelAgencyPortal/PackageInquiryController.php` and/or
> `backend/app/Http/Controllers/TravelAgencyPortal/PackageInquiryController.php`) and the
> booking-creation logic it reuses (likely `TravelBookingService` — see FIX-2 notes above for that
> service, at `backend/app/Services/Customer/TravelBookingService.php`) to understand how an
> inquiry becomes a booking (traveler count, package, pricing, customer). Add an equivalent
> `convertToBooking` (or `convert`) action to
> `backend/app/Http/Controllers/Admin/TravelPackageInquiryController.php`, reusing the same
> underlying service rather than duplicating booking-creation logic. Add a route in
> `backend/routes/admin.php` near line 1611-1613 (e.g.
> `POST /admin/travel/inquiries/{inquiry}/convert`), gated behind the appropriate admin permission
> (check how other admin travel routes gate permissions, e.g. a `travel.manage` or similar gate/
> policy — follow the existing convention in that routes file, don't invent a new permission scheme).
> Update `backend/resources/views/admin/travel/inquiries/index.blade.php` to add a "Convert to
> Booking" action button per row (with a confirm dialog), wired to the new route. On conversion,
> update the inquiry's status so it's clearly marked converted/booked and not actionable twice.
> Test by converting a real inquiry via the admin UI and confirming a TravelBooking row is created
> with correct traveler_count/pricing, and the inquiry is marked converted. Commit as one commit:
> "Fix: allow admin to convert booking inquiries into confirmed bookings (FIX-3)".

---

## FIX-4 — Filters not working on admin /orders

**Root cause:** Admin `/orders` is Blade + DataTables
(`backend/resources/views/admin/orders/index.blade.php` →
`backend/resources/js/components/datatable.js` → `Admin\OrderController::datatable()` (line 140) →
`buildOrdersQuery()` (line 62) → `HasDataTable::applyFilters()`). Most filters are wired correctly,
but two concrete bugs exist:
1. `min_total`/`max_total` filters cast to `(int) round((float) $v)`
   (`OrderController.php:98-99`), while `orders.total` is a real decimal column — this truncates
   cents and silently excludes/includes wrong rows for any non-integer amount (the common case).
2. The top toolbar quick-search box (`#orders-table-search`) only searches the `order_number`
   column (via DataTables' built-in `searchable_columns`), while the separate filter-panel "search"
   field searches order_number + customer name + email — two inputs behaving inconsistently, which
   reads as "filters broken" to admins expecting the top search to match customer name/email too.

**Prompt:**
> You are fixing FIX-4 in /var/www/marketplace, the admin orders filters at `backend/resources/
> views/admin/orders/index.blade.php` (served via DataTables →
> `backend/app/Http/Controllers/Admin/OrderController.php::datatable()`/`buildOrdersQuery()` around
> lines 62-140, using `HasDataTable::applyFilters()` from `backend/app/Traits/HasDataTable.php`).
> Two confirmed bugs to fix:
> 1. In `OrderController.php` around lines 98-99, the `min_total`/`max_total` filter closures cast
>    the input to `(int) round((float) $v)` before comparing against `orders.total`, which is a
>    real decimal money column (see `total_formatted` using `number_format($row->total, 2)` in the
>    same file's datatable row transformer, and confirm `Order.php` has no int cast on `total`).
>    Fix the filters to compare against the actual decimal value (cast input with `(float)`, not
>    `(int) round(...)`), so e.g. `min_total=149.99` correctly matches orders totaling 149.99.
>    Also double check `max_total`'s closure actually uses `<=` — re-read the two lines carefully,
>    there may be a copy-paste bug where both use the same comparison operator.
> 2. The toolbar quick-search box (`#orders-table-search` in datatable.js, submitted via DataTables'
>    built-in `search.value`) only searches `order_number` per `columnDefinitions()`'s
>    `searchable_columns` (`OrderController.php` around line 581+), while the separate filter-panel
>    "search" scope (lines ~87-91) also matches customer name/email. Make the toolbar quick-search
>    behave the same as the filter-panel search (order_number + customer name + email) — either by
>    routing the toolbar search through the same backend scope, or by widening
>    `searchable_columns`/the query builder used for the DataTables global search to match. Pick
>    whichever approach fits the existing `HasDataTable` trait pattern with the least duplication.
> Test by filtering orders with a decimal min/max total and by quick-searching a customer email in
> the toolbar box, confirming both return correct results. Commit as one commit: "Fix: correct
> admin orders total-range filter truncation and inconsistent search behavior (FIX-4)".

---

## FIX-5 — "How do I pay via bank transfer?" (customer confusion / lost instructions)

**Root cause:** Bank transfer IS fully implemented backend-side (gateway, bank details,
proof-of-payment upload: `backend/app/Services/Payments/BankTransferGateway.php`,
`backend/app/Services/Customer/BankTransferProofService.php`, upload route at
`backend/routes/api_customer_v1.php:460`) and mostly on the frontend
(`frontend/src/features/noon/checkout/success/bank-transfer-card.tsx` renders bank details + proof
upload). The real bug: `frontend/src/features/noon/checkout/success/index.tsx` (lines ~19-28) reads
the order **only from `sessionStorage.getItem("last_placed_order")`**, ignoring the
`initialOrderData` prop that's presumably fetched server-side. If the customer leaves the success
page (closes tab, opens an emailed/bookmarked confirmation link later, private browsing clears
storage), `order` is null and the page hard-redirects to `/` — the bank transfer instructions and
upload form become permanently inaccessible, even though the backend still has the data. There also
does not appear to be a fallback to view bank transfer details/upload proof from the regular order
history/detail page.

**Prompt:**
> You are fixing FIX-5 in /var/www/marketplace: customers report not knowing how to complete a
> bank transfer payment. The backend is complete (bank account details returned by
> `backend/app/Services/Payments/BankTransferGateway.php::initiate()`, proof-of-payment upload via
> `backend/app/Services/Customer/BankTransferProofService.php` and route
> `POST .../orders/{order}/bank-transfer-proof` at `backend/routes/api_customer_v1.php:460`). The
> bug is `frontend/src/features/noon/checkout/success/index.tsx` (~lines 19-28): it reads the order
> ONLY from `sessionStorage.getItem("last_placed_order")` and ignores the `initialOrderData` prop
> that appears to be passed in (presumably from a server-side fetch for direct/deep links). If
> sessionStorage is empty (tab closed and reopened, emailed confirmation link visited later, private
> browsing), the component treats `order` as null and redirects to `/`, permanently hiding the bank
> transfer instructions/upload form for that customer. Fix `success/index.tsx` to fall back to
> `initialOrderData` when sessionStorage is empty/invalid, instead of always redirecting home. Then
> check `frontend/src/features/noon/profile/orders/summary/components/payment-details-card.tsx`
> (or wherever the regular order-detail/summary page renders payment info) — if it does NOT already
> show bank transfer account details + a proof-upload option for orders paid via bank_transfer with
> no proof uploaded yet, add that, reusing the existing `bank-transfer-card.tsx` component/
> `use-bank-transfer-proof.ts` hook logic so customers can always find how to pay from their normal
> order history, not just the one-time post-checkout success screen. Test by placing a bank-transfer
> order, closing the tab, reopening the order via /orders/{orderNumber}, and confirming bank details
> + upload form are visible and functional. Commit as one commit: "Fix: make bank transfer payment
> instructions accessible after leaving checkout success page (FIX-5)".

---

## FIX-6 — Warranty purchases API doesn't include the warranty product; purchased warranty
## doesn't appear as claimable ("اشتريت منتج وخدت عليه ضمان بس منزلش في warranty claims")

**Root cause (two related bugs):**
1. **Missing product data:** `WarrantyPurchase` model
   (`backend/app/Models/WarrantyPurchase.php:26-44`) has no `product()` relationship (unlike
   `WarrantyClaim`, which does). `WarrantyController::purchases()`
   (`backend/app/Http/Controllers/Api/Customer/WarrantyController.php:70-82`) eager-loads only
   `['orderItem', 'plan']`. `WarrantyPurchaseResource::toArray()` (lines 41-44) synthesizes a
   `product` object purely from `orderItem->product_snapshot` JSON + `orderItem->sku` — no
   `product_id`, image, slug, or live product link — so if the snapshot is incomplete/missing, the
   product shows as unknown (frontend fallback "unknownProduct" at
   `frontend/src/features/noon/profile/warranties/index.tsx:74`).
2. **Newly purchased warranty invisible / not claimable:** Warranty purchases are created at
   checkout with `status: 'pending'`, `coverage_starts_at/ends_at: null`
   (`backend/app/Http/Controllers/Customer/CheckoutController.php:1222-1243`), and only become
   `active` with real coverage dates via `SubOrderObserver` when the sub-order is marked delivered
   (`backend/app/Observers/SubOrderObserver.php:40-68`). `WarrantyController::purchases()` applies
   an `->active()` scope (line 76), excluding ALL pending rows — so a warranty is invisible in "My
   Warranties" until delivery is recorded AND that recording path actually fires the observer. If
   any delivery-marking code path (manual admin status override, alternate fulfillment service,
   COD-capture listener, etc.) doesn't trigger `SubOrderObserver`, the purchase stays `pending`
   forever, explaining "bought a product with warranty but it never shows up as claimable."

**Prompt:**
> You are fixing FIX-6 in /var/www/marketplace, warranty visibility issues. Two sub-bugs to fix in
> one pass since they share the same files:
>
> **(a) Missing product info in warranty purchases API**
> (`GET /api/customer/v1/{country}/warranty/purchases`): `WarrantyPurchase` model
> (`backend/app/Models/WarrantyPurchase.php`) has no proper relation to the actual `Product` — only
> a snapshot copy via `orderItem->product_snapshot`. Add a real relationship (e.g.
> `product()` belongsTo/hasOneThrough via `orderItem->product_id`, matching however `WarrantyClaim`
> already does it — check its model for the pattern) so the purchase can resolve the live product
> (id, name, slug, image, current price) even when the snapshot is stale/incomplete. Update
> `WarrantyController::purchases()` (`backend/app/Http/Controllers/Api/Customer/
> WarrantyController.php` ~lines 70-82) to eager-load the new relation, and update
> `WarrantyPurchaseResource::toArray()` (~lines 41-44) to prefer live product data (falling back to
> the order_item snapshot only if the live product was deleted), including `product_id` and image/
> slug so the frontend can link to the product page.
>
> **(b) Purchased warranty never appears as claimable**
> Warranty purchases are created `pending` at checkout
> (`backend/app/Http/Controllers/Customer/CheckoutController.php` ~lines 1222-1243) and only
> activated by `SubOrderObserver` (`backend/app/Observers/SubOrderObserver.php` ~lines 40-68) when
> a sub-order is marked delivered. `WarrantyController::purchases()` filters to `->active()` only,
> hiding pending warranties entirely. First, grep the codebase for every place a sub-order's status
> gets set to delivered (admin manual override, fulfillment/shipping service, COD capture listener,
> etc.) and confirm each path actually fires the observer/event that activates the warranty — if you
> find a path that updates status directly (e.g. `SubOrder::where(...)->update(['status' =>
> 'delivered'])`, which bypasses Eloquent observers on bulk updates) rather than
> `$subOrder->update([...])` on a model instance, fix it to go through the model so the observer
> fires, or explicitly call the activation logic there. Second, decide product UX: should a
> `pending` (not-yet-delivered) warranty purchase show up in "My Warranties" as
> "upcoming/not yet active" instead of being hidden entirely? If so, relax the `->active()` scope in
> `purchases()` to also include `pending`, and have `WarrantyPurchaseResource`'s `is_claimable`
> (already correctly gated on `active` + coverage dates) clearly communicate status so pending ones
> render as non-claimable-but-visible rather than being silently dropped from the list. Test by
> creating an order with a warranty add-on, confirming it appears in "My Warranties" pre-delivery as
> pending, then marking the sub-order delivered through every relevant admin/fulfillment path and
> confirming the warranty flips to active+claimable each time, with correct product info shown.
> Commit as one commit: "Fix: warranty purchases show correct product info and become claimable
> after delivery (FIX-6)".

---

## Notes for whoever runs these

- Run FIX-1, FIX-4, FIX-5, FIX-6 in parallel — they touch disjoint files.
- Run FIX-2 before/independent of FIX-3, since FIX-3 reuses the booking-creation service FIX-2's
  investigation describes (no frontend dependency between them, but conceptually related).
- None of these issues are permission-config bugs; all required real code changes.
- After all fixes land, re-test the two originally-reported admin return/warranty URLs manually:
  - `https://admin.noon.codefanz.com/api/customer/v1/uae/warranty/purchases?page=1` (FIX-6)
  - `https://admin.noon.codefanz.com/api/customer/v1/uae/orders/NOON-20260918-0WPTDB/returns`
    (not covered by an issue above — if still broken after FIX-1/4/5/6, file as FIX-7 separately;
    it wasn't part of this investigation batch).
