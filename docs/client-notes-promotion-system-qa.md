# Client notes → Questions & Answers (Celebrity / Affiliate promotion system)

Source: `/var/www/marketplace/notes` (Arabic client requirements, machine-translated and partly garbled, so a few lines are my best reading — those are flagged **[interpretation]**).
Audited: 2026-10-11 · Branch `main` · Backend `backend/` (Laravel 12) · Storefront `frontend/` (Next.js)

## How to read this file

Status tags used on every question:

| Tag | Meaning |
|---|---|
| ✅ **Works** | Already in the code; steps below were verified by reading the code path |
| 🆕 **Added in this change** | Missing before; built now, covered by tests in `backend/tests/Feature/MarketerPromotionFlowTest.php` |
| 🟡 **Needs client decision** | Code exists but behaves differently from the note, or the note is ambiguous |
| ⛔ **Not built** | Still missing; a build plan is given |

### Panels and URLs

Local domains come from `backend/.env` (`APP_DOMAIN=noon.loc`); production uses the same sub-domains on the real domain.

| Panel | Base URL | Who |
|---|---|---|
| Admin | `http://admin.noon.loc` | Platform admin |
| Seller (Partner) | `http://partner.noon.loc` | Vendor / seller |
| Marketer | `http://marketer.noon.loc` | Influencer (celebrity) and Affiliate |
| Marketer mobile API | `/api/marketer/...` | Flutter marketer app |
| Storefront (Next.js) | `https://noon.codefanz.com/{locale}/...` | Customers |

Storefront pages that matter here: `/{locale}/r/{referral-code}` (referral landing), `/{locale}/marketers` (marketer directory), `/{locale}/marketer/{slug}` (marketer public profile), `/{locale}/qr-code` (QR scan page).

### Deploy first

This change adds one migration. **It has not been run on any database** — run it yourself:

```bash
cd backend && php artisan migrate   # 2026_10_11_100001_create_promotion_quota_tables_and_settings
```

Until then the new admin fields (promotion category / monthly target) will fail to save.

---

## Master lifecycle (one promotion, end to end)

```
SELLER (partner.noon.loc)                ADMIN (admin.noon.loc)                  MARKETER (marketer.noon.loc / WhatsApp)
─────────────────────────                ──────────────────────                  ───────────────────────────────────────
1. List product (stock ≥ category min)
   FBM/FBP: ship units to platform
   warehouse (warning shown 🆕)
2. Create promotion campaign
   /marketer-campaigns/create/{listing}
   picks N influencers / affiliates
        │  status = pending_admin
        ▼
                                         3. Review: set marketer + platform
                                            commission, approve (or auto-approve
                                            after N hours)
                                            /marketer-campaigns/{id}
        │  status = active
        ▼
                                         4. One invitation per marketer is created.
                                            Window = setting marketer_invitation_timeout_hours
                                            (default 12 h 🆕)
                                                                                 5. WhatsApp message with a signed
                                                                                    accept/reject link 🆕  +  in-app
                                                                                    notification + /invitations page
                                                         ┌──────────────────────┴───────────────────────┐
                                                    ACCEPT                                         REJECT / NO ANSWER
                                                         │                                               │
                                       fee per influencer (9 / 18 / 27…) 🆕 payer setting      status rejected / timed_out
                                       samples created (1 per influencer + platform)                    │
                                       referral code + QR + marketer listing                  6. Replacement chosen: same job type,
                                                         │                                        fewest invitations in last 30 days 🆕
                                                         │                                        → new invitation (chain via
                                                         │                                          replaced_invitation_id)
                                                         │                                        → seller, old marketer notified;
                                                         │                                          ADMIN LOG entry 🆕
                                                         ▼
                       7. Customer opens /r/{code} or scans QR → last-click attribution → order
                       8. Delivered + return window → conversion approved → pending balance
                       9. Clearing days → wallet balance; platform keeps its share of the net commission
                      10. Stock out / budget used → campaign done; monthly counter "2/3" updated 🆕
```

---

# PART 1 — Seller promotes a product with celebrities / affiliates

### Q1. When a seller finishes listing a product and wants it promoted by celebrities and affiliates, how does he opt in? ✅ / 🟡

**Answer.** Opt-in is a *campaign created from the listing*, not a checkbox on the product row.

Steps (seller):
1. `partner.noon.loc/listings` → open the listing; its fulfilment model must be allowed (setting `marketer_campaign_allowed_fulfilment_models`, default `fbn`,`fbm`).
2. `partner.noon.loc/marketer-campaigns/create/{vendorListing}` → pick marketers (search endpoint `partner.noon.loc/campaigns/search-marketers`), set commission type/amount, budget, submit (`POST partner.noon.loc/marketer-campaigns`).
3. Follow it at `partner.noon.loc/marketer-campaigns/{id}`.

**Known gap (not changed):** the listing edit form (`partner/listings/edit`) shows `influencer_commission_percentage` / `affiliate_commission_percentage` / `*_sample_quota`, but those four columns exist in **no database and no migration**, and `VendorListing::$fillable` does not include them, so what the seller types there is silently discarded (and `AdminListing` would error if it received them). The note says the *admin* sets those percentages per category (Q9), so I did not add the columns — decide whether this form block should be removed or backed by columns.

### Q2. What does the platform deduct from the seller? (the "fixed" and the "variable" admin commission, `1.900 SAR` or %) ✅ / 🟡

**Answer.** Both are charged on every sale by `CheckoutPricingEngine::resolveCommission`: **fixed amount + category percentage** (`CommissionCalculator::calculate(... includeFlat: true)`), per country and per fulfilment model (FBN columns vs FBP columns), with optional price tiers.

Where the admin sets it:
- Per category and country: `admin.noon.loc/categories/{id}/edit` (country commission block) and the consolidated editor `admin.noon.loc/commissions` (tab *Platform rules* → country overrides + price tiers).
- Per vendor: `admin.noon.loc/commissions` tabs *Vendor rules / Vendor flat rate / Vendor discount* (outranks the category chain).

🟡 Decisions still open (unchanged from `docs/commission-refactor/client-requirements-matrix.md`):
- **`1.900 SAR`** — 1.9 SAR or 1,900 SAR? Money is stored as integer base-currency units, so the two differ by 1000×.
- **FBM** has no rate of its own: everything that is not FBN uses the FBP columns. Say if FBM needs separate rates.

### Q3. Are the two commissions "pre-configured by the admin for whoever carries the responsibility — FBM, FBP or FBN"? ✅ / 🟡

**Answer.** Yes for FBN vs FBP columns (see Q2). The enum in code is `fbm | fbn | cross_dock` (there is no `fbp` value), so "FBP" = the non-FBN column set. Same open question as FBM above.

### Q4. How does the per-influencer platform fee work (1 influencer = 9, 2 = 18, 3 = 27 …)? ✅ / 🆕 / 🟡

**Answer.** Fee = `fee_per_influencer` × accepted influencers, one charge per accepted invitation.

- Admin sets the amount per country: `admin.noon.loc/marketer-settings` (card *Influencer fee per country*). 9 is **not** hard-coded.
- The note says the fee is deducted **from the seller**; the old code charged the **influencer's own wallet**. 🆕 I added a setting so the client can choose:
  `Settings → Marketer` (`admin.noon.loc/settings/group/marketer`) → **`marketer_influencer_fee_payer`**: `marketer` (default, old behaviour) or `vendor`.
  - `vendor`: at acceptance the seller wallet is debited. If the balance is short the acceptance still succeeds, the fee stays `pending`, and `CollectPendingInfluencerFeesJob` (daily 04:00) retries until the seller tops up. Admin can still mark it paid by hand (`PATCH admin.noon.loc/marketer-campaigns/{id}/invitations/{inv}/mark-fee-paid`).
  - Admin-owned campaigns (no seller) always fall back to the influencer wallet.
- 🟡 Decide which payer is right, then set the setting. "Weekly statement" collection from the note is implemented as *immediate debit + daily retry*, not a weekly batch.

### Q5. Are affiliates charged this fee? ✅

**Answer.** No. Only marketers with job key `influencer` are charged; affiliates get `platform_fee_status = not_applicable`.

### Q6. How is the influencer / affiliate commission decided per category (e.g. perfume = 18%)? ✅ / 🟡

**Answer.** Admin-defined, not seller-defined:
- Per country × category fixed amounts: `admin.noon.loc/marketer-settings` / `commissions` tab *Country × category amounts* (`POST admin.noon.loc/categories/{id}/marketer-commission`).
- Per marketer × category rules (%, fixed or both, with minimum and excluded categories): `admin.noon.loc/marketers/{id}` (section *Commission by category*) or `commissions` tab *Marketer category rules*.
- Per campaign amounts, editable before approval: `PATCH admin.noon.loc/marketer-campaigns/{id}/commission`.
- Resolution order is applied in `LastClickAttributionService::recordConversion`.

🟡 The country table is *amount-only*; confirm that a *percentage per category split by influencer/affiliate* (the "18%" example) is wanted there.

### Q7. How does the platform take its share of the marketer's net commission (100 → platform 30, marketer gets 70)? ✅ / 🟡

**Answer.** Per marketer the admin sets a commission discount: `admin.noon.loc/marketers/{id}` → profile form → *commission_discount_type / flat / percentage*. `MarketerProfile::applyCommissionDiscount()` reduces the marketer's gross commission (30 % discount: 100 → 70). Shown in the financial statements via the campaign financials page `admin.noon.loc/marketer-campaigns/financials`.

🟡 Confirm that "platform share" and "discount" are the same concept (I treat them as the same). The campaign-level `platform_commission_amount` is informational only and is not credited to a ledger (known gap G6 in `docs/marketer-campaign-flow.md`).

### Q8. How many samples are taken from the seller's stock and who gets them? ✅ / 🟡

**Answer.** Per category the admin sets: influencer samples per marketer, affiliate samples per marketer, and platform (admin) samples — `admin.noon.loc/categories/{id}/edit` (fields `influencer_sample_qty`, `affiliate_sample_qty`, `platform_sample_qty`).
- The platform sample is created when the campaign is created.
- Each accepted invitation creates that marketer's sample. So 2 influencers + admin = 3 sample rows if the quantities are 1/1/1.
- Tracking: admin `PATCH admin.noon.loc/marketer-campaigns/{id}/samples/{sample}` (pending → dispatched → delivered → returned); marketer `marketer.noon.loc/samples` (address + "received"); seller sees them on `partner.noon.loc/marketer-campaigns/{id}` and fills size/attributes via `.../samples/{sample}/custom-attributes`.

🟡 "1 + 1" is a configuration, not a fixed formula — set the three quantities to 1 to match the note.

### Q9. Affiliate samples — how many? ✅ / ⛔ (partly)

**Answer.** Default comes from `affiliate_sample_qty` per category (and the `min_affiliate_sample_quota` setting). The note says the admin decides per request; a per-request override is **not built** (the admin can still edit the sample row / category value).

### Q10. Samples are deducted from stock, normal admin commissions still apply, but the seller's financial statement must show price 0 labelled "promotion with marketers". Is that done? ⛔

**Answer.** Not built. Needs an accounting decision first: which ledger/statement lines carry the sample, does the commission apply to the real price or to 0, and does it feed `TaxInvoice`. Build plan:
1. New `order_item_allocations`/ledger line type `promotional_sample` with `price = 0`, `commission = standard rule`.
2. Seller statement view (`partner.noon.loc/finance/...`) renders the label *Promotion with marketers* / *ترويج مع المسوقين*.
3. Tests: sample dispatch writes a zero-price line; payout totals unchanged.
Do not start without the client's accounting rule.

### Q11. Is there a minimum stock the admin requires before a product can be promoted? ✅

**Answer.** Yes — `categories.min_stock_for_campaign` (default 10 in code), enforced when a campaign is created for vendor or admin listings ("Insufficient stock. Minimum N units required"). Admin edits it in `admin.noon.loc/categories/{id}/edit`. `MonitorCampaignStockJob` (hourly) pauses a campaign when stock falls and resumes after restock.

### Q12. FBP / FBM sellers must send the products to the platform warehouse, and the seller must be told. 🆕 / 🟡

**Answer.** 🆕 When a seller opens `partner.noon.loc/marketer-campaigns/create/{listing}` for a listing that is **not FBN**, a yellow warning now explains the units to be promoted (marketer samples + platform sample) must be sent to the platform warehouse (`partner.marketer_campaigns.ship_to_warehouse_notice`, EN + AR).
🟡 Not built: a receiving step that blocks the campaign until the warehouse confirms the units. Needs the client's rule on which fulfilment models must ship (default setting still allows `fbn`,`fbm`; `cross_dock` is blocked).

### Q13. The marketer gets a limited time to accept or reject (e.g. 12 h, set by the admin). Is that configurable? 🆕

**Answer.** Before: `dispatchInvitation` forced `max(168, setting)` — i.e. never less than 7 days, so a 12 h window was impossible. Fixed: `max(1, setting)` with default **12**.
- Admin sets it at `admin.noon.loc/settings/group/marketer` → **`marketer_invitation_timeout_hours`**.
- The migration moves the setting from the untouched legacy default 168 to 12 (a value an admin already changed is left alone).
- On expiry `ProcessInvitationTimeoutJob` marks the invitation `timed_out` and starts the replacement (Q14).

### Q14. If the marketer rejects or doesn't answer, the system should pick another marketer — who, and who is told? 🆕

**Answer.** `MarketerCampaignService::replaceMarketer`:
1. Candidates = active marketers of the **same job type** never invited to this campaign (and with at least `marketer_replacement_min_accepted_campaigns` accepted campaigns).
2. 🆕 Ordered by **fewest invitations received in the last 30 days**, then fewest accepted — matching the note's "the one with the fewest requests last month".
3. A new invitation is dispatched; `replaced_invitation_id` links the chain.
4. Notifications: old marketer + seller get `CampaignInvitationRejectedNotification`; seller gets `MarketerReplacedNotification` (names old and new marketer); 🆕 **every admin** gets `CampaignMarketerReassignedNotification` — the admin log of "who was moved to whom and why (rejected / timed_out)", with a link to `admin.noon.loc/marketer-campaigns/{id}`. If nobody is available the admin log still records "no replacement marketer available".

The new marketer receives the normal invitation (Q15). The admin can also invite more marketers manually: `POST admin.noon.loc/marketer-campaigns/{id}/invite-marketers`.

### Q15. The request goes to the celebrity on WhatsApp with a link to accept or reject; the seller is told the result. 🆕 / 🟡

**Answer.** Before: the WhatsApp job never sent anything (provider call commented out, a `WhatsAppService` class did not exist) **but still set `whatsapp_sent = true`**, and the message linked to POST-only routes that need a login. Now:
- 🆕 `App\Services\WhatsAppService` — provider chosen by setting `whatsapp_provider` (`meta` = WhatsApp Cloud API, or `log`), with `whatsapp_meta_phone_number_id` and `whatsapp_meta_access_token` (encrypted) under `admin.noon.loc/settings/group/integrations`. `send()` returns true only when the provider accepted the message; `whatsapp_sent` is set only then. With the default `log` provider nothing is delivered and the flag stays false.
- 🆕 The message carries one **signed, expiring link** (`marketer.noon.loc/invitations/{id}/whatsapp?signature=…`), valid until the invitation expires. It opens a page where the marketer presses **Accept** or **Reject** (+ optional reason) **without logging in**; tampered/expired links return 403. The action goes through the same service as the dashboard buttons (contract-accepted and active-account checks still apply).
- Seller feedback: in-app notifications for accepted / rejected / replaced (`partner.noon.loc` notification bell). 🟡 The seller does **not** receive a WhatsApp message about the outcome; add it through `WhatsAppService` if required.
- ⚠️ To go live: create a WhatsApp Business Cloud API app, set the three settings, and use an approved message template for first-contact messages (Meta only allows free text inside the 24 h window; the current implementation sends plain text).

### Q16. Barcode / attribution: each celebrity promotes with the product barcode and the sale goes to the one whose promotion the customer saw. ✅ / 🟡

**Answer.** Every accepted invitation gets a referral code, link `…/r/{code}` and a QR image (`GenerateEntityQrCodeJob`, `qrcodes/invitations/{id}.png`). Customers land on the storefront page `/{locale}/r/{code}`; `LastClickAttributionService` stores the click (TTL = `marketer_attribution_window_days`) and at checkout `resolveAndRecordConversion` credits the **last** marketer (priority: marketer listing in cart → last click → none). QR pages: `admin.noon.loc/qr/marketer/{slug}`, `/{locale}/qr-code`.
🟡 "Re-pointing the barcode on hand-over to a replacement" is satisfied by the replacement getting a fresh code; there is no migration of an old printed barcode to the new marketer.

### Q17. Commissions are net, shown in admin statements; example 100 → 30 / 70. ✅

**Answer.** See Q7. Marketer side: `marketer.noon.loc/finance/commissions` and `/finance/wallet`; API `GET /api/marketer/finance/commissions`. Lifecycle: conversion `pending` → `approved` (after delivery + return window; `ApproveMarketerConversionsJob` 03:15) → `wallet_released` (after clearing days; `ReleaseMarketerPendingCommissionJob` 03:30).

### Q18. "All commissions are set per country." ✅

**Answer.** Yes: `category_commission_tiers.country_id`, `country_categories` overrides, `marketer_commission_country_settings`, `marketer_influencer_fee_country_settings` all key on country. VAT on the commissions themselves is 🟡 **not applied** (VAT is applied to customer line totals only) — needs an accounting decision.

---

# PART 2 — Marketing model between celebrities themselves and the platform

### Q19. What are the four celebrity categories and how are they represented? 🆕

**Answer.** `App\Enums\PromotionCategory`:

| # | Name (EN) | Name (AR) | Meaning from the note |
|---|---|---|---|
| 1 | Vendor Requests | طلبات التجار | Promotions sellers request from this celebrity; monthly minimum e.g. 3 |
| 2 | Peer Influencer Products | منتجات المسوقين الآخرين | Celebrity picks N products from *other celebrities' own stores*, earns commission, platform cut per category |
| 3 | Admin Intermediary | وساطة الإدارة | Admin places ads (existing platform products or new ones); celebrity picks from them; commission per sale |
| 4 | Nawy Own Products | منتجات ناوي الخاصة | Same mechanism as 3 for the platform's own products |

🆕 Case **4** was missing from the enum; added (with EN/AR labels).
Assign a marketer to a category: `admin.noon.loc/marketers/{id}` → profile form → **Monthly promotion obligation** → *Promotion category*.

### Q20. How does the admin set the minimum number of ads per month for each category? 🆕

**Answer.** `admin.noon.loc/marketer-settings` → card **Monthly promotion minimums**: choose category, country (or *All countries*), minimum; save (`POST /marketer-settings/promotion-targets`, permission `marketer_fee_settings.edit`) or delete a row. Resolution for a marketer (`PromotionQuotaService::targetFor`): per-marketer override → category target for the marketer's country → category target for all countries → none.
The note says the admin can let a celebrity go above the limit — nothing caps the counter, so exceeding is already allowed.

### Q21. Each celebrity sees "2/3" in his panel; red + alert when he hasn't activated his ads. 🆕

**Answer.** `marketer.noon.loc/dashboard` shows a **Monthly promotion target** card (`done/target` + progress bar, category name). It turns **red with a warning** only in the last `marketer_promotion_reminder_days` days of the month (default 7) while he is still short; before that it is neutral/amber. Also returned by `GET /api/marketer/dashboard` as `promotion_quota` for the Flutter app. "Done" = invitations the marketer **accepted this calendar month**.
🟡 Confirm: the note could also mean "red at any time he is behind"; that is a one-line change in `PromotionQuotaService`/the card.

### Q22. A WhatsApp alert in the last week for a celebrity who is short, and the contract benefits/obligations. 🆕 / ⛔

**Answer.** 🆕 `php artisan marketers:promotion-quota-reminders` (scheduled daily 09:00 in `routes/console.php`): for each active marketer with a category who is behind in the last-week window it sends an in-app notification (`PromotionQuotaBehindNotification`) and a WhatsApp message through `WhatsAppService`, **once per marketer per month**.
⛔ **Financial penalties / "contract fines" are not built** and must not be built without written rules (amounts, grace, who is charged). The existing `promotion:apply-penalties` command is for *seller* promotions (`promotions` table), unrelated to marketers.

### Q23. Category 2 — the celebrity chooses N products from other celebrities' stores, at most one product per other celebrity per month, with the platform's deduction shown. ⛔

**Answer.** Only the category flag and counter exist. Build plan:
1. Table `marketer_peer_picks` (`marketer_id`, `owner_marketer_id`, `marketer_listing_id`, `month`) with a unique `(marketer_id, owner_marketer_id, month)` → enforces "one per owner per month".
2. Marketer page `marketer.noon.loc/peer-products` (list other marketers' *own* listings, hide the owner's identity as the note says: "the celebrity must not know which celebrity owns it").
3. Commission = admin per-category rule (Q6) minus platform share (Q7); show the platform deduction on the pick screen.
4. Counts toward the category-2 monthly counter.

### Q24. Category 3 (intermediaries) and 4 (Nawy products) — admin posts ads, the celebrity picks and earns per sale. ✅ (flow) / ⛔ (screens)

**Answer.** The mechanics exist: admin creates a campaign on an **admin listing** (platform-owned product) at `admin.noon.loc/marketer-campaigns/create` (`POST` same path), invites marketers, commission per sale as in Q6. What is missing:
- a way to tag a campaign/listing as category **3 vs 4** (no column yet), and
- separate admin screens per category.
Plan: add `promotion_category` (tinyint, null) to `marketer_campaigns`, filled by the admin create form; filter tabs on `admin.noon.loc/marketer-campaigns`; marketer `campaigns/active` grouped by category; counter in Q21 filtered by it.

### Q25. "When we add our own products in Nawy there should be an option on the same product page to show it in category 3 or 4 — without removing the backup page to add products per category." ⛔

**Answer.** Not built. Plan: checkbox group *Offer to celebrities as: [3 Admin intermediary] [4 Nawy product]* on `admin.noon.loc/admin-listings/create|edit`, stored as `admin_listings.promotion_category`; when set, auto-create the admin campaign through `MarketerCampaignService::createCampaign` (owner = platform). Keep the existing per-category add screen as the fallback.

### Q26. "Only the admin sets the minimum stock for a promotion." ✅

**Answer.** See Q11.

### Q27. "Which products are high-quality products — the market decides." [interpretation] 🟡

**Answer.** No ranking logic is tied to this sentence. If it means "only top-rated/best-selling products may be offered to celebrities", the signals exist (`ProductBestsellerRanking`, reviews) — confirm the rule before building.

---

## What changed in the code (summary)

| Area | Change | Files |
|---|---|---|
| Invitation window | no longer forced to ≥ 168 h; default 12 h | `Services/MarketerCampaignService.php`, `database/seeders/SettingsSeeder.php` |
| Replacement | fewest invitations in last 30 days; admin log for every move | `MarketerCampaignService::replaceMarketer`, `Notifications/Admin/CampaignMarketerReassignedNotification.php` |
| WhatsApp | real sender + honest `whatsapp_sent`; signed no-login accept/reject page | `Services/WhatsAppService.php`, `Jobs/SendCampaignWhatsAppNotificationJob.php`, `Http/Controllers/Marketer/InvitationWhatsAppController.php`, `resources/views/marketer/invitations/whatsapp.blade.php`, `routes/marketer.php` |
| Fee payer | seller or influencer pays; seller-paid retried daily | `MarketerCampaignService::acceptInvitation/collectInfluencerFee`, `Jobs/CollectPendingInfluencerFeesJob.php` |
| Monthly obligation | category 4, targets table, per-marketer category/override, dashboard card, API field, reminders | `Enums/PromotionCategory.php`, `Models/PromotionCategoryTarget.php`, `Services/Marketer/PromotionQuotaService.php`, `Console/Commands/SendPromotionQuotaReminders.php`, admin settings + marketer show views |
| Seller warning | non-FBN ship-to-warehouse notice | `resources/views/partner/marketer_campaigns/create.blade.php` |
| Schema/settings | one migration | `database/migrations/2026_10_11_100001_create_promotion_quota_tables_and_settings.php` |

Tests: `backend/tests/Feature/MarketerPromotionFlowTest.php` — 8 tests, all passing (window setting, replacement + admin log, signed links incl. replay, honest WhatsApp flag, quota target resolution/warning window, seller-paid and marketer-paid fee).
Pre-existing failures in `tests/Feature/Marketer*` (7 failures + 2 errors, e.g. `ApproveMarketerConversionsJob::handle()` argument, profile ad-price tests) fail identically **without** these changes and were not touched.

## Decisions I need from the client

1. `1.900 SAR` = 1.9 or 1,900?
2. Who pays the influencer fee — seller (note) or influencer (old code)? Then set `marketer_influencer_fee_payer`.
3. Does FBM need its own commission rate? What does FBP map to (`cross_dock`)?
4. Is "platform share of net commission" the same as the per-marketer commission discount?
5. VAT on commissions: which fees, which rate, invoice format?
6. Zero-price sample line in the seller statement: accounting rule (Q10).
7. Contract fines for missing the monthly minimum: amounts and rules (Q22).
8. Red warning only in the last week, or whenever behind (Q21)?
9. Seller outcome by WhatsApp, or in-app only (Q15)?
10. Remove or back with columns the listing-form influencer % block (Q1).
