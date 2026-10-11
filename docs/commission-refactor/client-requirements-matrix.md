# Commission requirements — traceability matrix

Status of this document: **built from the requirement summary in `Claude_Commissions_Followup_Completion_QA.md`, not from the client's Arabic PDF.** The PDF is not in the repository, so wording and numbers below (9 SAR, 12 h, 18 %, 30 %, `1.900 SAR`) are as relayed and are unverified against the original.

"Verified" means the code path was opened and read (file/class named). "Not found" means searched with the stated keywords and nothing consumed it; it is not proof of absence.

Status values: **Implemented · Partially implemented · Missing · Not applicable · Needs client decision**.
Scope values: **UI** = shown on the `/commissions` page; **Separate** = a feature/workflow outside the page refactor.

---

## Part 1 — Client requirements 1–24

| # | Requirement (concise) | Implementation found | Status | Scope | Test evidence | Blocker / decision |
|---|---|---|---|---|---|---|
| 1 | Seller can opt a product into influencer/affiliate promotion at listing time | `Partner\ListingController` + `Vendor\ListingService` write `influencer_commission_percentage`, `affiliate_commission_percentage`, `*_sample_quota` — **but no migration creates the two percentage columns on `vendor_listings` and none of the checked databases has them** | Partially implemented (likely broken) | Separate | None for these columns; `/commissions` "Listing influencer/affiliate %" tab reports the missing columns (`CommissionsControllerTest::test_listing_percentages_tab_reports_missing_schema_columns`) | Confirm with the vendor-listing owner whether the columns were meant to exist; do not add schema without that |
| 2 | Fixed admin commission (`1.900 SAR`) **or** %, plus variable category commission, per category and fulfilment model (FBN/FBP/FBM) | `CheckoutPricingEngine::resolveCommission`: `commissions` rows → `vendors.commission_rate` → category chain (`commission_{fbp\|fbn}_{pct\|fixed}`, country override, price tiers). **Only two models exist in calculation: FBN, and "everything else" uses the FBP columns. No FBM rate.** `FulfillmentModel` enum = `fbm`, `fbn`, `cross_dock` (there is no `fbp` value) | Partially implemented | UI (read) + Separate | `CommissionsVendorRulesTest::test_saved_rule_sets_the_commission_charged_at_checkout`; existing `CommissionRuleResolverTest`, `CommissionReconciliationTest` | **Client: does FBM need its own rate? What does "FBP" map to in the enum?** |
| 3 | 9 SAR fee per selected influencer (1=9, 2=18…); platform revenue; deducted from the **seller's** weekly statement; retried when balance is insufficient | `MarketerInfluencerFeeCountrySetting.fee_per_influencer` (per-country setting). `MarketerCampaignService::acceptInvitation` (~L770-806) debits it **from the influencer's own marketer wallet at acceptance**, per accepted invitation, status `pending`→`paid`. No seller-statement deduction, no weekly collection, no retry found | **Differs from client description** — Needs client decision | Separate | None targeting the fee | **Who pays: seller (PDF) or influencer (current code)? Weekly deduction and retry are not built.** 9 SAR is a country setting, not hard-coded |
| 4 | Affiliate promotion is free of this fee | Same method: fee only when `$marketer->isInfluencer()`; affiliate → `platform_fee_status = 'not_applicable'` | Implemented | Separate | None found | — |
| 5 | Per-category influencer/affiliate commission (e.g. perfume 18 %) | `marketer_commission_country_settings` (influencer vs affiliate amount per category+country, **fixed amounts**) and `marketer_commission_rules` (%/fixed/both per marketer, scope, category). Resolution order read in `LastClickAttributionService::recordConversion` | Partially implemented | UI (tabs "Country × category amounts", "Marketer category rules") | `CommissionRuleResolverTest`, `MarketerCommissionRulesApiTest`, `MarketerCategoryCommissionStoreTest` | Confirm a *percentage per category split by influencer/affiliate* is wanted — the country table is amount-only |
| 6 | One sample per selected influencer + one for admin (2 influencers → 3 units) | Per-category `influencer_sample_qty`/`affiliate_sample_qty`; campaign snapshots `platform_sample_qty_snapshot`, `per_marketer_sample_qty_snapshot`; `MarketerCampaignSample` rows created on acceptance; setting `admin_mandated_sample_quota` | Partially implemented | Separate | `MarketerSamplesLifecycleTest` | Quantities are per-category settings, not hard-coded "1 + 1 admin"; confirm formula |
| 7 | Sample: normal admin commissions apply, but seller statement shows price 0 + "promotional sample" note | Not found (searched sample/ledger code) | Missing / Needs accounting design | Separate | — | Needs accounting/ledger design before any code |
| 8 | Affiliate samples decided by admin per request | `affiliate_sample_qty` per category; `min_affiliate_sample_quota` setting | Partially implemented | Separate | — | Per-request admin override not found |
| 9 | Admin-set minimum inventory for promotion | `categories.min_stock_for_campaign` (default 10 in code), enforced in `MarketerCampaignService` campaign creation for vendor and admin listings (`Insufficient stock…`) | Implemented | Separate | None found targeting it | — |
| 10 | FBP/FBM sellers must ship to admin warehouse before promotion; warn seller | Campaign creation rejects some fulfilment models ("Campaigns are not allowed for … listings"); no seller warning/ship-to-warehouse step found | Partially implemented | Separate | — | Client: audit says FBM = FBP rate; PDF treats them as distinct — **kept as two separate findings, not reconciled** |
| 11 | Acceptance window (e.g. 12 h); auto-replace on reject/expiry | Campaign `auto_approve_at` (admin approval, hours config) exists; **no invitation expiry/auto-replacement found** | Missing | Separate | — | Define window, expiry job, replacement rules |
| 12 | Replacement = influencer with fewest requests last month | Not found | Missing (proposal only) | Separate | — | Client to confirm algorithm |
| 13 | Notify seller, old & new influencer, admin log of reassignment | `Vendor\CampaignInvitationRejectedNotification` exists for rejection; no reassignment flow | Missing | Separate | — | Depends on #11 |
| 14 | WhatsApp message with accept/reject link; tell seller outcome | `SendCampaignWhatsAppNotificationJob` dispatched from `MarketerCampaignService` (~L661). **The send is a stub: the provider call is commented out (`// VERIFY: … WhatsAppService::send`), `WhatsAppService` does not exist, yet the job sets `whatsapp_sent = true`** | Partially implemented (flag set without sending) | Separate | None | Provider choice; the `whatsapp_sent` flag currently over-reports |
| 15 | Category 1: monthly minimum seller-requested ads, progress `2/3`, red warning, final-week notice | `PromotionCategory::VendorRequests = 1` enum case exists; no consumer found; no monthly counters/targets (the only `monthly_min*` hits are acquisition commissions) | Missing | Separate | — | Admin-configurable per-category monthly minimums |
| 16 | Category 2: pick ≥N products from other influencers; max one per owner per month; show platform deduction | `PromotionCategory::PeerInfluencerProducts = 2` defined; **no same-owner/month restriction found** | Missing | Separate | — | — |
| 17 | Category 3 (intermediaries): admin assigns products, per-sale commission | `PromotionCategory::AdminIntermediary = 3`; `marketer_profiles.broker_category_id` / `broker_city_id`; no admin assignment screen traced | Partially implemented | Separate | — | Trace which flow consumes `broker_category_id` |
| 18 | Category 4 (Nawy-owned products), same mechanism as 3 | **Enum has only cases 1–3; no category 4** | Missing | Separate | — | Decide whether 4 is a new enum case or a flag on 3 |
| 19 | Last-week WhatsApp alert if short of a minimum; contractual fines | Not found. No fines should be built without business rules | Missing | Separate | — | Fines need explicit rules + approval |
| 20 | Nawy Now product form: option to list in category 3/4; separate admin screens per category | Not found | Missing | Separate | — | Depends on #17/#18 |
| 21 | Barcode/attribution follows the latest influencer whose promotion the customer saw | `LastClickAttributionService` (`resolveAndRecordConversion`, `recordConversion`, `last_click` commission type); `GenerateEntityQrCodeJob`, `CustomerQrCodeService`, `qr_code_path` exist. Barcode re-pointing on hand-over not traced | Partially implemented | Separate | `MarketerAttributionConversionTest` (see baseline notes) | Do not change attribution priority without regression tests |
| 22 | Platform keeps a profile-configured % of the marketer's *net* commission (100 → 30/70) | `MarketerProfile::applyCommissionDiscount()` (flat or %) reduces the marketer's computed commission; `platform_commission_amount` is a separate campaign-level amount. **No per-profile "platform share %" field found; whether discount == platform share is undecided** | Needs client decision | UI (shown + labelled) / Separate | `tests/Unit/CommissionDiscountTest` (vendor discount); marketer discount not specifically tested | Define "net commission"; decide whether to reuse the discount or add a field |
| 23 | VAT on all commissions per country | `countries.vat_rate`; `CheckoutPricingEngine` applies VAT to **customer line totals** (`round(amount × vat_rate/100)`). **No VAT on commissions/fees found** | Needs client decision | Separate | Existing checkout tests cover customer VAT only | Accounting decision: which fees are taxable, rates, invoicing |
| 24 | Ambiguous `1.900 SAR` | No such constant in code. `1900` appears only as a seeder price | Needs client decision | — | — | **Client must say 1.9 or 1,900.** Money is stored as base-currency integers (no `/100`), so the stored value differs by 1000× depending on the answer |

---

## Part 2 — Technical audit items 1–20 → `/commissions` tabs (updated for Phase 3)

**Tab type:** *Editor* = settings are edited inside the tab; *Editor (partial)* = some fields editable, the rest read-only for a stated reason; *Read-only* = no write path exists.

| # | Item | Tab key | Tab type | Effect (verified) | What is editable in the tab / endpoint reused | Why the rest is read-only | Tests |
|---|---|---|---|---|---|---|---|
| 1 | Platform rules | `platform-rules` | Editor (partial) | Applied | **Country FBP/FBN overrides** (`PUT categories/{id}/country-commission`, `categories.edit`) and **price tiers** (`commission-tiers` store/update/destroy). Overlap validation is the existing server rule | Base `categories.commission_{fbp,fbn}_*` has no admin writer (category form does not save it; engine uses it only as last-resort fallback). Legacy `categories.commission_rate` / bulk-commission is **not exposed**: the engine never reads it | `CommissionsInPageEditingTest` (override save/validation/permission, tier create/overlap/update/delete, page markup) |
| 2 | Vendor-specific rules | `vendor-rules` | Editor | Applied; outranks flat rate and category chain; `rate_type` ignored | Full CRUD, `commission_rules.manage`, audit log, platform-wide confirmation | `tiered` offered nowhere (engine ignores it) | `CommissionsVendorRulesTest` (27, incl. checkout end-to-end) |
| 3 | Vendor flat rate | `vendor-flat-rate` | Editor | Applied only when > 0 | `PATCH commissions/vendors/{id}/commission` (`section=flat_rate`), **`vendors.edit` + assigned-vendor scope**, same rule array as the vendor form (`UpdateVendorRequest::commissionRules()`), audit log | — | `CommissionSettingsControllerTest` |
| 4 | Vendor discount | `vendor-discount` | Editor | Applied after calculation | Same endpoint, `section=discount`; blank amounts → NOT NULL defaults | — | `CommissionSettingsControllerTest`, `CommissionDiscountTest` |
| 5 | Subscription-plan discount | `plan-discount` | Read-only + link | **Not applied** (no callers) | — | Making it editable would imply an effect that does not exist | page test labels it not applied |
| 6 | Platform-as-seller | `admin-listing` | Read-only | System | — | Nothing configurable | render test |
| 7 | Flash `commission_override_pct` | `flash-override` | Read-only + link | Analytics only | — | Edited on the flash sale itself; not a charge | render test |
| 8 | Campaign commission | `campaigns` | Editor (partial) | Applied | **Marketer/platform amounts** for campaigns *pending admin review* (`PATCH marketer-campaigns/{id}/commission`), **approve** and **reject** (existing actions) | Commission type, budget and tier rules: no admin write path in code | `CommissionsInPageEditingTest` (incl. default-guard regression) |
| 9 | Marketer category rules | `marketer-rules` | Editor | Applied; mirrors to legacy tables in one transaction | Create/update (`POST marketers/{id}/category-commissions`) and delete, scopes products / open market / travel, % / fixed / both, minimum, exclusions; `marketers.manage` | Platform-default rules (no marketer): no writer exists, adding one would create a precedence layer. `travel` scope saves but no consumer was found (labelled in the form) | `MarketerCategoryCommissionStoreTest` (7), `CommissionsInPageEditingTest` |
| 10 | Legacy rule stores | `legacy-stores` | Read-only | Not applied (mirrors) | — | Nothing reads them | `CommissionsControllerTest` |
| 11 | Country × category amounts | `country-amounts` | Editor | Applied as fallback | `POST categories/{id}/marketer-commission` (`marketer_commission_settings.edit`), returns to the same tab | — | `CommissionsInPageEditingTest` |
| 12 | Listing influencer/affiliate % | `listing-percentages` | Read-only | Not applied; **columns missing** | — | Columns do not exist in any checked database; adding them needs a decision (see Part 5) | `CommissionsControllerTest` |
| 13 | Marketer discount | `marketer-discount` | Read-only + link | Applied | — | Meaning vs "platform share" undecided; no thin write endpoint was added | render test |
| 14 | Flash marketer bonus | `flash-bonus` | Editor (partial) | Applied | **Invite marketers with a bonus** (`POST flash-sales/{id}/invite-marketers`, `flash_sales.edit`, confirmation because it notifies) | An existing invitation's bonus cannot be edited (no path; `firstOrCreate`) | `CommissionsInPageEditingTest` |
| 15 | Platform share of campaign | `platform-share` | Read-only + link | Applied | The platform amount is edited in the **campaigns** tab (pending campaigns) | Set at approval | render test |
| 16 | Acquisition agents | `acquisition` | Editor | Applied (internal earning) | Assign / update / revoke via `commissions/vendors/{id}/acquisition…` (**`vendors.edit` + assigned-vendor scope**, existing validation and `VendorAcquisitionController` write path reused) | — | `CommissionSettingsControllerTest` |
| 17 | Travel platform commission | `travel-platform` | Read-only | Report only | — | Config value; no ledger/payout deduction found | render test |
| 18 | Travel offers | `travel-offers` | Read-only | Offer rate stored; scope not consumed | — | **No admin write route exists** for these offers; they are managed in the travel-agency panel | render test |
| 19 | Shipping-rule commission | `shipping-rules` | Editor (partial) | `commission_*` **not applied**; `extra_delivery_fee` **applied** | Admin listings only (`POST admin-listings/{id}/shipping-rule`, `admin_listings.edit`); the form separates "stored only" from "applied to shipping fees" | Vendor-listing rules are managed in the partner panel | `CommissionsInPageEditingTest` |
| 20 | Related fees | `related-fees` | Read-only + link | Reference | Link to influencer-fee settings | Not commissions | render test |

All 20 tabs render in `en`/`ar`, are permission-gated, and are covered by `CommissionsControllerTest` and the browser passes.

## Part 3 — Client-vs-audit mismatches to resolve

1. **Who pays the influencer fee** — PDF: seller, weekly. Code: the influencer's own wallet, at acceptance.
2. **FBP vs FBM** — PDF lists FBN/FBP/FBM; code has `fbm`/`fbn`/`cross_dock` and resolves everything non-FBN through the FBP columns.
3. **Four influencer categories** — PDF: four; code: enum with three.
4. **WhatsApp** — PDF: delivered with accept/reject link; code: stub that marks `whatsapp_sent = true`.
5. **VAT** — PDF: on all commissions; code: VAT only on customer line totals.
6. **Platform share of marketer commission** — PDF: per-profile %; code: per-profile discount (flat or %) with no confirmation they are the same concept.

---

## Part 4 — Classification of the client requirements (Phase 3 vocabulary)

The PDF itself was **not available**, so every row below rests on the requirement summary supplied in the brief plus code inspection.

| Classification | Requirement IDs |
|---|---|
| Implemented and verified | #4 affiliate free (`MarketerCampaignService::acceptInvitation`), #9 minimum stock for a campaign (`categories.min_stock_for_campaign`) |
| Partially implemented | #1 (columns missing), #2 (no FBM rate), #5, #6, #8, #10, #14 (stub), #17, #21 |
| Contradicted by current code | #3 — the fee is debited from the **influencer's wallet** at acceptance, not from the **seller's** weekly statement; #14 — `whatsapp_sent = true` is recorded although nothing is sent |
| Missing | #7, #11, #12, #13, #15, #16, #18 (enum has 3 cases), #19, #20 |
| Requires client decision | #2/#10 (FBM), #3, #22 (net commission, platform share vs discount), #23 (VAT), #24 (`1.900`) |
| Out of scope for the UI phase | #6–#8 (sample accounting), #11–#20 (promotion workflow, notifications, monthly obligations) — separate features |

## Part 5 — Items deliberately not made editable, with the decision they wait on

| Item | Why it is not editable | Decision / next step |
|---|---|---|
| Subscription-plan discount | Stored but never applied at checkout | Decide whether to wire `SubscriptionService::applyCommissionDiscount` into checkout |
| Flash `commission_override_pct` | Analytics-only | Decide whether it should become a real override |
| Listing influencer/affiliate % | `vendor_listings` has no such columns and no migration creates them, yet listing code writes them | Confirm intended semantics; then add a migration (proposal: `decimal(5,2) null` on `vendor_listings` and `admin_listings`) |
| Platform-default marketer rules | No writer exists | Confirm intended precedence before adding one |
| `travel` rule scope | No consuming flow found | Trace/define the consumer |
| Travel platform commission | Report figure only | Decide whether it is a ledger deduction |
| Commission `tiered` type | Engine ignores it | Decide whether tiers should apply to `commissions` rows |
| FBM-specific rate | Non-FBN uses FBP columns | Client decision |
| Base category FBP/FBN columns | No writer; fallback only | Decide whether they should remain a fallback |
| Legacy `categories.commission_rate` | Written by bulk-commission but never read | Remove or document as legacy |
| Travel offers | No admin write route | Decide whether admin should edit/approve them here |

## Part 6 — Snapshot mismatch (order items)

`CheckoutController` (L1211) fills `commission_rate_pct`, `commission_fixed` and `commission_category_id` from `CheckoutCalculationService::calculateCommission`, which treats a listing as FBN when `global_system_type === express_fbn`. L1229 then overwrites only `commission_amount` with the engine's `raw_commission` (FBN when `fulfillment_model === 'fbn'`). `vendor_listings.global_system_type` **defaults to `express_fbn`**, so an FBM listing created without setting it records the FBN pair beside an amount charged with the FBP pair (fixture: stored 12 % + 300, charged 900 = 10 % + 200 × 3). The dev database's only vendor listing is exactly this combination.

* Charged amount: unchanged and covered (`CommissionSnapshotInvariantTest::test_charged_commission_for_an_fbm_listing_uses_the_fbp_pair`).
* Current snapshot pinned by a characterization test; the intended invariant is a documented skipped test.
* **Minimal safe fix (not applied):** populate the three snapshot fields from the engine's own resolution (rate, fixed, category id) for *new* orders only. Existing order items must not be rewritten.
