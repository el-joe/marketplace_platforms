# Admin permission sweep — 2026-11-23 (WS4a, client #16b)

**Convention:** route middleware `admin.permission:<perm>` (`App\Http\Middleware\CheckAdminPermission`, guard `admin`, `$admin->can()` so `super_admin` passes via `Gate::before`). Section gate on the `Route::prefix()` group (`*.view`), action gate on each mutating route. Some older controllers additionally check inside actions (`abort_unless(hasPermissionTo(...))`, policies). The middleware now accepts any-of: `admin.permission:a|b`. Sidebar items are filtered by `NavigationService::adminNavigation()` `permission` keys.

**Audit (1119 admin routes, `route:list --json`, admin domain):**

| | Before | After |
|---|---|---|
| Routes with no permission check at all (route or controller) | 175 | 0 (+49 allow-listed: login/logout/password reset, profile, own notifications, own acquisition commissions, locale/country switch, stop-impersonating, broadcasting auth, 2 redirects, 30 read-only `docs/*` pages) |
| Routes gated only inside the controller (no route middleware) | 28 | 0 (route middleware now mirrors the check) |
| Mutating routes gated only by a `*.view` permission (no stronger check anywhere) | 163 + 18 weak in-controller checks | 0 (4 read-only POST calculators/data feeds allow-listed) |

## New permissions (migration `2026_11_23_115354_add_admin_permission_sweep_permissions`)

Every new permission goes to `super_admin`, plus every role holding the "granted alongside" permission (the permission that already opened that page in the sidebar). Mutating permissions are only derived from an existing mutating permission.

| Permission | Granted alongside | Local roles that received it |
|---|---|---|
| `orders.edit` (status change, assign shipping, ship) | `orders.cancel` | super_admin, operations_admin |
| `subscriptions.view` / `.manage` | `transactions.view` / `transactions.edit` | super_admin |
| `fbn.view` / `.manage` (inbound, marketplace rules) | `warehouses.view` / `warehouses.manage` | view: super_admin, operations_admin; manage: super_admin |
| `fbn_storage_fees.view` / `.manage` (invoice/pay/generate/rules/recalculate) | `warehouses.view` / `warehouses.manage` | view: super_admin, operations_admin; manage: super_admin |
| `contracts.view` / `.manage` | `classifieds.view` / — | super_admin |
| `classifieds.manage` (approve/reject listings, verify attachments) | — | super_admin |
| `acquisition_commissions.view` / `.manage` | `vendors.view` / `vendors.edit` | view: super_admin, operations_admin, vendor_relations_admin; manage: super_admin, vendor_relations_admin |
| `delivery.view` / `.manage` | `payouts.view` / `payouts.approve` | super_admin |
| `wallets.view` / `.manage` | `payouts.view` / `payouts.approve` | super_admin |
| `shipping_companies.view` | `settings.view` | super_admin |
| `ai.view` / `.manage` | `dashboard.view` / `settings.edit` | view: all roles with dashboard.view; manage: super_admin |

The `admin` seeder role gets them automatically (all-but-sensitive); `operations` seeder role gets `orders.edit` via its `orders.*` filter. Role form groups: AR/EN labels under `admin.permission_groups.*`.

## Changed gates (route → permission)

Group gate shown as `G:`; action gates replace "only the group `*.view`" unless noted.

| Area | Routes (`admin.` prefix) | Gate now |
|---|---|---|
| **Subscriptions** (#16b) | `subscriptions.*` (index, show, plans.index, invoices.index, datatables) | G: `subscriptions.view` (was none) |
| | `subscriptions.plans.store/update/toggle-active/destroy`, `subscribe-vendor`, `cancel`, `invoices.mark-paid` | `subscriptions.manage` (was none) |
| **FBN** | `fbn.inbound.*`, `fbn.marketplace.*` | G: `fbn.view` (was none) |
| | `fbn.inbound.approve/reject/tracking/receive`, `fbn.marketplace.store/update/destroy` | `fbn.manage` |
| | `fbn.storage-fees.*` (index, datatable, generation-status, rules index, pending-impact) | G: `fbn_storage_fees.view` (was none) |
| | `fbn.storage-fees.generate`, `.status` (mark invoiced/paid), `free-period-rules.store/update/destroy/recalculate-pending` | `fbn_storage_fees.manage` |
| | `fbn.storage-fees.revert-status` | `fbn_storage_fees.view` + `fbn_storage_fees.revert_status` (WS5) |
| **Orders** | `orders.update-sub-order-status`, `orders.update-status`, `orders.sub-orders.assign-shipping` | `orders.edit` (was `orders.view`) |
| | `orders.sub-orders.ship` | `orders.edit` (permission did not exist → only super_admin could ship; now grantable) |
| | `orders.force-cancel`, `orders.cancel-items` | `orders.cancel` |
| | `orders.refund` | `orders.refund` |
| | `orders.dispute` / `orders.flag-fraud` | `orders.dispute` / `orders.flag_fraud` |
| **Delivery** | `delivery.*` | G: `delivery.view` (was none) |
| | agents store/update/destroy/suspend/activate/reset-password/assign-zone, documents verify/reject, zones store/update/destroy/assign-agents, assignments auto/manual-assign | `delivery.manage` |
| | `delivery.payouts.generate/approve/process`, `delivery.cod-settlements.generate/settle/dispute` | `payouts.approve` |
| **Wallets** | `wallets.*` | G: `wallets.view` (was none) |
| | `wallets.adjust` | `wallet.manual_adjust` |
| | `wallets.freeze/unfreeze`, `withdrawals.approve/reject/processed`, `cod-settlements.run/settle` | `wallets.manage` |
| **Shipping companies** | `shipping-companies.*` | G: `shipping_companies.view` (was none) |
| | `approve`, `suspend`, `fallback-rules.store/destroy`, `supervisors.toggle-notifications` | `settings.edit` (same as existing create/update/delete) |
| **Contracts** | `contracts.templates.*`, `contracts.customer.*`, `classifieds/contract-templates` redirect | G: `contracts.view` (was `classifieds.view` / none) |
| | templates create/edit/store/update/publish/retire/reactivate/default/destroy | `contracts.manage` |
| **Classifieds** | `classifieds.listings.index/show` | `classifieds.view` (was none) |
| | `classifieds.listings.approve/reject`, `classifieds.attachments.verify` | `classifieds.manage` (was none) |
| **Acquisition commissions** | `acquisition-commissions.index/datatable`, `vendors.acquisition-agent.show` | `acquisition_commissions.view` (was `vendors.view`) |
| | `vendors.acquisition-agent.store/update/revoke` | `acquisition_commissions.manage` |
| **Travel** | `travel.agencies.*`, `bookable-units.*`, `packages.*`, `bookings.*`, `inquiries.*`, geography indexes | G: `travel.view` (was none / in-controller) |
| | agencies approve/reactivate, bookable-units approve/status, packages approve/categories.sync, inquiries.convert | `travel.approve` (bookable-units used to accept `travel.view`) |
| | agencies reject, bookable-units reject, packages reject | `travel.reject` |
| | agencies suspend, packages expire | `travel.suspend` |
| | `travel.countries/cities/inclusions/categories.store/update/destroy` | `travel.geography.manage` (was none) |
| **AI** | `ai.index` / `ai.credits.allocate` | `ai.view` / `ai.manage` (was none) |
| **Carrier** | `carrier-claims.*` / resolve, under-review | `carrier-claims.view` / `carrier-claims.resolve` (route-level mirror of controller check) |
| | `carrier-scorecard.*` | `payouts.view` (mirror) |
| **Dashboard** | `dashboard` | `dashboard.view` (mirror) |
| **Products** | bulk | `products.edit\|delete\|publish` + per-action check in controller |
| | generate-variants, upload-image | `products.create\|products.edit` |
| | delete-image, update-country-setting, reorder-images, hide, restore, variants.hide, frequently-bought-together add/remove/reorder, product-highlights store/update/destroy | `products.edit` |
| | destroy, variants.destroy | `products.delete` |
| | cost.show/calculate · cost.save/check-competitors · admin-listings cost-references store/update/destroy | `products.cost_data.view` · `products.cost_data.edit` (cost refs used `.view`) |
| **Catalog** | brands store/update/destroy/logo · warranty-plans store/update/toggle/destroy | `brands.create/edit/delete` · `warranty_plans.create/edit/delete` |
| | categories store · destroy · reorder/update/toggles/sync-attributes/image · shipping-methods CRUD | `categories.create` · `categories.delete` · `categories.edit` |
| | custom-pages store · destroy · other mutations | `categories.create` · `categories.delete` · `categories.edit` |
| | attributes store · update/destroy/values CRUD/reorder | `attributes.create` · `attributes.edit` |
| **Vendors** | bulk | `vendors.edit\|suspend\|payouts.hold` + per-action check in controller |
| | update, request-info, assign-manager, account-manager, notify, team deactivate/reactivate, lock/unlock, vendor-listings update/ships-to | `vendors.edit` |
| | approve, reject · suspend, reactivate · blacklist · strikes · hold/release-hold | `vendors.approve` · `vendors.suspend` · `vendors.blacklist` · `vendors.strikes.issue` · `vendors.payouts.hold` |
| | documents verify/reject, bank-accounts.verify | `vendors.documents.verify` |
| **Finance** | payouts approve/process/hold/recalculate | `payouts.approve` |
| | transactions confirm-bank-transfer / reject-offline-payment | `transactions.edit` (controller checked `.view`) |
| **Geography** | countries store/update/destroy/deactivate/reactivate/shipping-settings/category-overrides · launch · cities CRUD/bulk-import | `countries.edit` · `countries.launch` · `countries.edit` |
| | currencies update/dispatch-update/refresh-rates/update-rate | `settings.edit` |
| **Marketing** | flash-sales transition/invite-vendors | `flash_sales.edit` |
| | coupon-participation-invitations store/cancel/requests approve/reject/mark-paid | `coupons.edit` |
| **Content** | blog categories/posts, adsupport collections/articles, helpcenter categories/articles, live-streams — all mutations | `pages.manage` |
| **Warehouses** | store, update, toggle-active, exceptional-zones, inventory.adjust, transfers store/ship/receive/cancel, vendor-limits, shipping-surcharges store/update/toggle | `warehouses.manage` (controller checked `.view`) |
| **Settings** | newsletter.destroy, system-tools inventory-reconcile/buybox-rebuild, shipping-settings.carriers.test | `settings.edit` (was `settings.view` / `analytics.view`) |

Sidebar (`NavigationService`) now uses the same permission as each page: AI, FBN (3), storage fees, acquisition commissions, contracts (2), delivery (5), carrier claims, shipping companies, wallets, subscriptions (3); also fixed 3 pre-existing mismatches (ad slots → `ad_slots.view`, paid ad bookings → `ad_bookings.view`, shipping zones → `settings.view`). Subscription plan buttons are wrapped in `@can('subscriptions.manage')`.

## Regression tests
`tests/Feature/Admin/AdminPermissionSweepTest.php`: every admin route gated (explicit allow-list); no mutating route gated only by `*.view` (allow-list: read-only POSTs and 23 controllers that check a stronger permission inside each action); sidebar permission equals the route gate; AR/EN group labels; FAQ-only admin → 403 on subscription plans GET/POST/PUT/toggle/DELETE and FBN screens/actions; fee viewer cannot generate/edit rules; `orders.view` only → 403 on all order actions; super admin still manages plans.

There are 22 new tests. Existing test fixtures that relied on ungated or view-only actions now grant the right permission (contracts, FBN fees, order cancel, coupons, custom pages, live streams, warehouses, transactions, wallets, travel reject). Suite: 1607 pass, 1 known failure (MarketerContractAuditTrailTest), 3 skipped.

## Decisions needed
1. Existing roles with only `X.view` lose mutating actions that were never gated (e.g. `vendor_relations_admin` has vendors.view/approve/suspend/edit but not `vendors.documents.verify`, `vendors.blacklist`, `vendors.strikes.issue`, `vendors.payouts.hold` → can no longer verify documents/bank accounts, blacklist, strike or hold). Grant those via Roles if intended.
2. `contracts.manage` and `classifieds.manage` went to super_admin only (no existing mutating permission to derive from).
3. Delivery management derives from `payouts.approve` (sidebar used `payouts.view`); ops roles may need `delivery.view/manage` granted.
4. `docs/*` pages stay readable by every admin (internal documentation, no data). Gate them if needed.
5. Live-only roles (e.g. `qa_faqs_only_test`) get only what the mapping grants; review roles in the role form after migrating.
