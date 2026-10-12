# Full change report — last 3 days (2026-10-09 → 2026-10-12)

Branch `main` · 51 commits · 627 files · +82,688 / −32,467 lines (≈ 56k of the added lines are the `backups/marketplace_platform.sql` dump; real code/doc churn is far smaller).
Sources: `git log --since="3 days ago"`, commit bodies, and the docs written during the period
(`docs/plans/marketer-campaign-tab.md`, `docs/plans/handoff/HANDOFF-marketer-campaigns.md`, `docs/client-notes-promotion-system-qa.md`, `COMMISSIONS_REPORT.md`).

> **Honesty note.** Everything below is read from commits/diffs and their commit messages. Automated tests were reported green by the authoring sessions (≈140 campaign tests, 500 tests in the H2 regression run). **No browser/manual QA of the three panels has been done yet** (the handoff file says so), and the live Meta WhatsApp send has never been exercised.

## 0. Deploy checklist (do this first)

`php artisan migrate:status` on this machine shows **11 migrations Pending** (all dated 2026-10-12). The three 2026-10-11 ones are already `Ran`.

```bash
cd /var/www/marketplace/backend
php artisan migrate:status | grep Pending
php artisan migrate
php artisan db:seed --class=SettingsSeeder      # only if new settings keys are missing (see §4)
php artisan queue:work --stop-when-empty        # jobs: WhatsApp, fee collection, samples
```

| Migration | Adds |
|---|---|
| `2026_10_12_000001_add_marketer_campaigns_delete_permission` | permission `marketer_campaigns.delete` (granted to vendor roles that already hold `cancel`) |
| `…000002_add_stock_deducted_at_to_marketer_campaign_samples` | `samples.stock_deducted_at` (stock taken once) |
| `…100001_add_type_cancel_and_commission_to_marketer_campaign_samples` | sample `type` (admin/influencer/affiliate), status `cancelled`, commission snapshot |
| `…100002_extend_payout_items_for_promotion_samples` | payout item types `influencer_fee`/`promotion_sample`, `description`, `campaign_sample_id` (commit `7446349f` made it re-runnable with `hasColumn` guards) |
| `…200001_add_vat_and_statement_settlement_to_influencer_fees` | VAT snapshot on the invitation fee, weekly-statement settlement |
| `…300001_add_whatsapp_delivery_tracking_to_campaign_invitations` | `whatsapp_attempts / failed_at / last_error / sent_at` |
| `…400001_add_platform_cut_to_marketer_profiles_and_conversions` | `marketer_profiles.platform_cut_percentage`, cut/VAT/net snapshot on conversions |
| `…500001_add_promotion_penalties_fines_and_reminders` | `marketer_promotion_fines`, `marketer_promotion_reminders`, penalty amount per target |
| `…600001_add_marketer_listing_source_to_marketer_campaigns` | `marketer_campaigns.marketer_listing_id` (peer promotion), `marketer_listing` source type |
| `…700001_add_referral_grace_days_setting` | setting `marketer_referral_grace_days` (7) |
| `…800001_add_replaced_at_and_resend_tracking_to_campaign_invitations` | `invitations.replaced_at`, WhatsApp resend counters |
| `…800002_create_marketer_sale_settlements_table` | `marketer_sale_settlements` (marketer-as-seller payouts) |

## 1. Day by day

### 2026-10-09 — localisation sweep + VPS deployment
| Commit | What |
|---|---|
| `b52a5be2` Fixes (138 files) | Big Arabic/English language pass: `lang/{ar,en}/admin.php, common.php, marketer.php, partner.php, js.php, mail.php, travel.php, customer_api.php` (hundreds of new keys); ~60 admin Blade views switched from hard-coded text to `__()` keys (page builder config forms, payment gateways, warehouses, shipping subsidies, live streams, newsletter, marketer contract pages…); seeders (FAQ, help center, portal content, settings) cleaned; Next.js components (checkout footer, flights booking, seller ratings, warranty dialog, classified page) text fixes; Flutter `travel_app` login/main; Postman customer collection updated. |
| `211bf044` Fixes | **Deployment kit**: `deploy/server-setup.sh`, `deploy/full-install.sh`, `deploy/deploy.sh`, `deploy/certbot-and-turn.sh` and the 962-line `docs/VPS_DEPLOYMENT.md` (nginx, PHP, MySQL, Node, SSL, TURN server for live streams). |

### 2026-10-10 — hardening, mail, real-time
| Commit | What |
|---|---|
| `4b390fa6` | Next.js `frontend/middleware.ts` → `frontend/proxy.ts` (Next 16 file convention) + AR locale fixes + favicon. |
| `c25881c3`, `70ec209f` | `config/cors.php` allowed origins; one-line `routes/api.php` fix. |
| `936cfba6`, `707ba90b` | Route-group / broadcast channel authorization fixes in `routes/{admin,carrier,delivery,marketer,partner,travel}.php` and `routes/channels.php`. |
| `de8e7603`, `8b37a1e6` | Echo (websocket) setup in `resources/js/shared/echo-setup.js`; carrier layout + portal JS fixes. |
| `eee9df33` New Mail | `NewVendorApplicationAdminMail` — admins are e-mailed when a vendor applies (`Portal/RegistrationController`, AR/EN templates). |
| `f05c6228` | `PaymentGatewayFactory`: COD is never treated as an "offline" (bank-transfer) gateway; frontend checkout/gift-card no longer demand a payment proof for COD. |
| `18613c6d`, `6610e6cb` | `deploy/deploy.sh` tweak; DB dump added to `backups/`. |

### 2026-10-11 — COD fulfilment, commission editor, promotion system v1
| Commit | What |
|---|---|
| `10fd3c59`, `b6b14af4` COD | COD order approval rolls up correctly: `OrderStateMachine`, `ShipmentService` creates the shipment so the carrier sees the order, admin order page shows the fulfilment panel (`admin/orders/{id}`), `CodApprovalRollupShipTest`; plan in `docs/plans/COD_ORDER_FULFILLMENT_PLAN.md`. |
| `f4228497` Fixes Related With Commissions | **Unified commission editor** `admin.noon.loc/commissions` (tabs: platform rules, vendor rules, vendor flat rate, vendor discount, country × category amounts, marketer rules, campaigns, flash bonus, acquisition, shipping rules), permission `commission_rules.manage`; snapshot-invariant tests; commissions QA briefs in `docs/`. |
| `da445ff8` | `COMMISSIONS_REPORT.md` — inventory of every commission type in the platform. |
| `edf043b8` Fixes | Promotion system v1 from the client's `notes`: 12 h invitation window, signed WhatsApp accept/reject link, replacement by "fewest requests in 30 days" + admin notification, category 4, monthly targets + "2/3" card + reminders, fee payer setting, **FBM commission pair**, **fixed fees as `DECIMAL(19,4)`** (1.9), **VAT on platform commission** (deducted from vendor payout, ledger `tax_payable`), listing opt-in columns. Docs: `docs/client-notes-promotion-system-qa.md`, `docs/دليل-اختبار-نظام-الترويج-QC.md`, `notes_with_answers.md`. |

### 2026-10-12 — marketer campaign refactor (phases 1 + 2) and test hardening
Planned in `docs/plans/marketer-campaign-tab.md` (R1–R12, prompts P1–P15). Commits in order:

| # | Commit | What changed |
|---|---|---|
| P1 | `6c7b6e08` | Partner CRUD endpoints for campaigns (edit/update/destroy), **several campaigns per listing**, a marketer cannot be on two open campaigns of the same listing, permission `marketer_campaigns.delete`. |
| P2 | `84929080` | Listing page "Marketer Campaign" tab = stat tiles + paginated table with view/edit/invite/cancel/delete; new `edit` view; disabled "New campaign" button shows the reason (inactive / fulfilment / min stock). |
| P3 | `4047f8dc` | **Vendor no longer types commission**. `MarketerCampaignQuoteService` quotes fee total + per-marketer commission from admin settings; server recomputes; affiliates = "open to all" checkbox; only budget cap + notes stay editable. |
| P4 | `be699444` | Sample accounting v1 (stock deducted on dispatch), min-stock gating on create page, FBM/FBP notice (create + show pages). |
| P5 | `47e91266` | Vendor notifications titled accept/reject/timeout ("searching replacement"); admin campaign page lists marketer transfers (from → to, reason, time). |
| — | `f0b7ec0d`, `6f2b686f`, `15e84ff9`, `fa0acab5` | Gap report R1–R12, phase-2 plan, tab plan, test baseline restored (Vite stub, onboarding gate fixtures, enum casts). |
| P8 | `c8af771d` | **Samples complete**: one fixed admin sample per campaign + one per influencer (created at invitation dispatch), affiliates = one platform row; replacement re-points the sample; cancel/reject cancels undispatched ones; dispatch (row-locked) takes stock once and snapshots admin commission; weekly statement shows a **0-priced `promotion_sample` line**. |
| P9 | `63633ff6` | **Influencer fee + VAT** (`countries.vat_rate`): wallet debit = fee + VAT, or deducted in the weekly statement as an `influencer_fee` payout item (never both); admin mark-fee-paid uses same guard; fixes partner payout page 500. |
| P10 | `fe9cabd9` | **WhatsApp gateway** (`log` default, `meta` driver), localized templates, signed confirm-then-POST links, retry/backoff, failure tracking on the admin campaign page, optional vendor WhatsApp. |
| P11 | `52fded88` | **Admin cut %, VAT, net**: gross → cut = gross × `platform_cut_percentage` → profile discount reduces the *cut* → VAT on the cut → net = gross − cut − VAT; wallet credited/clawed back with the net; ledger entries. *Behaviour change:* the discount no longer lowers the gross commission. |
| P12 | `2f372821` | **4 categories, quotas, fines**: per-category counting, per-category penalty, `promotion:close-month` (idempotent fines), once-per-month last-week reminder, admin listing category 3/4 option, promotion-products page, fines list. |
| P13 | `3ab3a675` | **Influencer-to-influencer promotion** (category 2): marketer promotes another influencer's own-FBN listing, one promotion per promoter+owner per calendar month, no self-promotion, owner stock/seller used at checkout, owner pays the promoter commission. |
| P14 | `2349dbbb` | **Referral code lifecycle**: single attribution authority `ReferralCodeResolver`, grace setting `marketer_referral_grace_days` (7), code-history chain on admin page, fresh code/QR per invitation. |
| P11/P10/P13/P14 follow-up | `77ef7525` | Marketer-as-seller settlement (`SettleMarketerSalesJob` 03:20, table `marketer_sale_settlements`), full ledger posting for commissions, admin **Resend WhatsApp** button + cooldown, `marketer_peer_campaign_min_stock`, `invitations.replaced_at`; fixes admin/marketer wallet views 500. |
| — | `8ac169ab`, `b1c9517f` | Final gap report + handoff doc (what remains = QA). |
| fix | `af576938` | Approving a vendor campaign resolves the admin default commission (no more 500 on zero commission). |
| fix | `98a02c27` | Influencer paid the influencer rate (not the affiliate default); vendor payout page lists sample lines. |
| tests | `20841738`, `2c7a6b45`, `fd258a83`, `cbafb0ff`, `f34b0e2f`, `9eacfc0a`, `4839a4f2` | 5-chain cross-panel lifecycle suite; global `withoutVite()`; **bug fix: wallet-only orders were always cancelled** (`orders.total` string compared with `=== 0`); **bug fix: clearing a coupon's `shipping_type_restriction` 500'd**; commission reconciliation test updated for commission VAT; regression triage table. |
| `7446349f` Fixes | Migration `100002` made idempotent. |

## 2. New / changed screens by panel

| Panel | Pages (local base URL) |
|---|---|
| **Admin** `http://admin.noon.loc` | `/commissions` (editor) · `/marketer-settings` (fee per country, monthly minimums + penalty, referral grace, peer min stock) · `/marketer-campaigns`, `/{id}` (approve, samples, transfers, WhatsApp failures/resend, fee status, code history) · `/marketer-campaigns/financials` · `/marketers/{id}` (cut %, discount, category, monthly override, sale settlements) · `/promotion-products` (category 3/4) · `/marketer-fines` · `/admin-listings/create|edit` (field *promotion category*) · `/categories/{id}/edit` (FBP/FBM/FBN pairs, samples, min stock) |
| **Partner** `http://partner.noon.loc` | `/listings/{id}` tab *Marketer Campaign* · `/marketer-campaigns`, `/create/{listing}`, `/{id}`, `/{id}/edit` · `/payouts/{payoutNumber}` and `/finance/transactions` (influencer fee, sample lines) |
| **Marketer** `http://marketer.noon.loc` | `/invitations` · signed `/invitations/{id}/whatsapp?signature=…` (no login) · `/samples` · `/campaigns/active`, `/campaigns/peer-products`, `/campaigns/promoted-by-others` · `/dashboard` (quota card) · `/finance/commissions`, `/finance/wallet` (net breakdown, sale lines) |
| API | `GET /api/marketer/dashboard` (`promotion_quota`), `partner.listings.marketer-fee` quote endpoint |

## 3. Scheduler (`backend/routes/console.php`)

| Time | Job / command | Purpose |
|---|---|---|
| hourly | `MonitorCampaignStockJob` | pause/resume campaigns by stock |
| daily 03:15 | `ApproveMarketerConversionsJob` | pending → approved (+ cut/VAT/net snapshot, ledger) |
| daily 03:20 | `SettleMarketerSalesJob` | marketer-as-seller payouts |
| daily 03:30 | `ReleaseMarketerPendingCommissionJob` | pending → wallet |
| daily 04:00 | `CollectPendingInfluencerFeesJob` | retry seller-paid fees |
| daily 09:00 | `marketers:promotion-quota-reminders` | last-week WhatsApp/in-app reminder, once per month |
| monthly 1st 00:30 | `promotion:close-month` | fines for the month just ended |

## 4. Settings introduced / changed

`marketer_invitation_timeout_hours` (12) · `marketer_influencer_fee_payer` (`vendor`) · `marketer_promotion_reminder_days` (7) · `marketer_referral_grace_days` (7) · `marketer_whatsapp_resend_cooldown_minutes` (10) · `marketer_peer_campaign_min_stock` · `marketer_campaign_min_platform_sample_qty` / `…_min_influencer_sample_qty` (1) · `commission_vat_enabled` · `whatsapp_provider` (`log`|`meta`) · `whatsapp_meta_phone_number_id` · `whatsapp_meta_access_token` · `marketer_campaign_allowed_fulfilment_models`.

## 5. Behaviour changes that can surprise people

1. Vendors cannot type a commission any more; admin settings decide (resolved at approval).
2. The marketer's commission discount now lowers the **platform cut**, not the gross commission. Wallet is credited with **net = gross − cut − VAT**, so with VAT > 0 the "100 → 70" example becomes 68.5 (5 % VAT on a 30 cut).
3. The influencer fee now carries VAT and is charged to the seller by default (wallet, else weekly statement).
4. Platform commission has VAT (deducted from `vendor_payout`) and fixed fees accept fractions (1.9).
5. Wallet-only checkout now works (it used to cancel every order).
6. Replacement no longer sets status `replaced`; it sets `replaced_at` so reasons (rejected / timed_out) are kept.

## 6. Known gaps / not verified

- No manual browser QA; real Meta WhatsApp never exercised (needs an approved template for first contact).
- Commission-snapshot display can disagree with the charged amount when a listing's `global_system_type` and `fulfillment_model` differ (pinned by `CommissionSnapshotInvariantTest`; product decision).
- Commission VAT is not recalculated on partial refunds.
- Still open client questions: meaning of "1.900" (1.9 vs 1,900), whether VAT applies to the marketer's own commission/fee in every case, and the quality-products rule ("the market decides").
- Local DB has 11 pending migrations (§0); until they run, most 2026-10-12 screens will fail with missing columns.

## 7. Tests added/changed

`tests/Feature/Campaigns/*` (fee, samples ×4, WhatsApp, ledger statement, marketer-sale settlement, peer promotion ×4, referral codes ×4, quota P12, lifecycle), `MarketerPromotionFlowTest`, `Commission/FbmFractionalFeeAndCommissionVatTest`, `Commission/CommissionSnapshotInvariantTest`, `Orders/CodApprovalRollupShipTest`, plus fixes to stale checkout/panel tests. Run: `cd backend && php artisan test --compact tests/Feature/Campaigns`.
