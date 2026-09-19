# Paid-Ad Billing: separate subscription fee from usage spend — sub-agent runbook

## Verified problem
`paid_ad_bookings.total_charged` is overloaded:
- FIXED (daily/weekly/monthly) bookings: set to `quoted_amount` at payment (`AdBillingService::collect` x2, `AdBookingService::markOfflinePaid`). That is the **subscription fee**, not delivery spend.
- CPM/CPC bookings: incremented per delivery in `AdBillingService::recordDelivery` (real usage spend).

Consequences already seen:
1. `recordDelivery` ends with `total_charged >= budget_amount` -> `complete('budget_exhausted')`. For fixed bookings `budget_amount` is NULL, so the first click/impression completed the booking (booking `ADB-20260919-ILWT3`). A one-line guard (`! isFixed()`) is ALREADY applied at `AdBillingService.php` (~line 188); keep it, but the real fix is to stop mixing the two meanings.
2. Every reader that shows "spend" mixes fee and usage, and papers over it with `total_charged ?: (quoted_amount + tax_amount)` (5 places).
3. `FinancialReportService::adSpendByCountry` sums `total_charged`, `AdAttributionService` uses it as ROI `ad_spend`.

## Target model (decision — confirm before running if you disagree)
Money is base-currency integers everywhere. NO `/100` or `*100` on money (percent math like VAT is the only exception).

| Column | Meaning after change |
|---|---|
| `quoted_amount` | UNCHANGED. Fixed: subscription subtotal (ex-tax). CPM/CPC: the budget subtotal. |
| `tax_amount` | UNCHANGED. |
| `budget_amount` | UNCHANGED. CPM/CPC only; NULL for fixed. |
| `unit_rate` / `agreed_rate` | UNCHANGED. Price per unit: fixed = per day/week/month, CPC = per click, CPM = per 1,000 impressions. |
| `clicks_delivered`, `impressions_delivered`, `cpm_impressions_billed` | UNCHANGED (already exist; these are the "unit" counters). |
| **`total_charged`** | **REDEFINED: usage-based spend only** (sum of `cpm`/`cpc` charges). Always 0 for fixed bookings. |
| **`subscription_charged`** (NEW, bigint unsigned, default 0) | Fixed fee actually collected, ex-tax. Set when payment is collected (wallet / payout_deduction / offline). 0 for CPM/CPC. |

Derived (accessors on `PaidAdBooking`, NO columns): `total_spend = subscription_charged + total_charged`, `effective_cpc = total_spend / clicks_delivered`, `effective_cpm = total_spend / impressions_delivered * 1000` (null when denominator is 0; integer base-currency rounding, no `/100`).
`paid_ad_charges` stays the audit ledger (`type` already distinguishes fixed / cpm / cpc / budget_reserve / refund) — do not change its schema.

## Rules for every agent
Match surrounding code style. Do NOT touch files owned by another prompt. Do NOT commit. Do NOT run migrations against `marketplace_platform_live` — use the test DB (`php artisan test`). Report changed files + anything unverified. Prompt A must finish first; B and C run in parallel after A; D last.

---

## PROMPT A — Schema + model + backfill (owns: database/migrations (new), app/Models/PaidAdBooking.php)
1. New migration `add_subscription_charged_to_paid_ad_bookings`: `subscription_charged` BIGINT UNSIGNED NOT NULL DEFAULT 0 after `total_charged`. Follow the style of `2026_09_19_090000_add_offline_proof_fields_to_paid_ad_bookings.php`.
2. Backfill in the same migration (idempotent, `down()` reverses it): for bookings whose `pricing_model` starts with `fixed_` (check `PaidAdSlotPricingModel::isFixed()` values) and `total_charged > 0` and `payment_status` in paid/reserved/partially_refunded: `subscription_charged = total_charged; total_charged = 0`. Leave CPM/CPC rows untouched. Cross-check against `paid_ad_charges` (type `fixed`, ex-tax = amount - tax_amount) and log rows that disagree instead of silently changing them.
3. `PaidAdBooking`: add `subscription_charged` to `$fillable` and `$casts` (integer); add accessors `total_spend`, `effective_cpc`, `effective_cpm` exactly as specified above (Attribute::get style used elsewhere in the models folder).
4. Do not edit `marketplace_platform.sql` by hand.

## PROMPT B — Billing + booking services (owns: app/Services/Ads/AdBillingService.php, app/Services/Ads/AdBookingService.php, app/Jobs/Ads/*)
1. Everywhere fixed payment is collected set `'subscription_charged' => $b->quoted_amount` and STOP setting `total_charged` to `quoted_amount`: `AdBillingService::collect` (wallet fixed branch ~line 58, payout_deduction fixed branch ~line 101) and `AdBookingService::markOfflinePaid` (~line 428).
2. `recordDelivery`: keep the `! $pricingModel->isFixed()` guard on the completion check; additionally early-return the charging math for fixed (only update counters + daily stats). Fixed bookings complete ONLY via `PaidAdSchedulerJob` (period end) or cancel. Ensure CPM/CPC `total_charged` still only sums usage charges and never exceeds `budget_amount`.
3. `AdBookingService::complete`: unspent-budget refund stays CPM/CPC + wallet only (verify it uses usage `total_charged`, which is now correct). `computeCancelRefund`: fixed branch prorates `quoted_amount` by remaining days (ex-tax) while the customer paid `quoted_amount + tax_amount` — verify and, if the refund should include prorated tax, fix it; otherwise document why not. Non-fixed branch (`budget_amount - total_charged`) is now correct.
4. `RecordPaidAdEventsJob` and `PaidAdSchedulerJob`: confirm no other reader of `total_charged` assumes the old meaning (grep again).
5. `PaidAdDailyStat.spend` for fixed bookings stays 0 (subscription is not daily usage spend) — do not allocate it.

## PROMPT C — Readers: reports, resources, controllers, views, i18n (owns: FinancialReportService, AdAttributionService, Http/Resources/Vendor/Ads/AdBookingResource, Partner|Vendor|Marketer|Api AdBookingController(s), views partner/ad-bookings, admin/paid-ad-bookings, marketer/promote/bookings, lang/{en,ar}/admin.php + ads keys)
1. Replace every `total_charged ?: (quoted_amount + tax_amount)` fallback (Partner/AdBookingController ~81, Marketer/PromoteBookingController ~82, views partner/ad-bookings/show ~81, marketer/promote/bookings/show, AdAttributionService ~46) with `total_spend` (fee + usage). Where a booking-list column is sorted (`orderable_column => paid_ad_bookings.total_charged`) order by `(subscription_charged + total_charged)` via raw expression or keep usage-only if the column is labelled that way — pick one and make label and sort agree.
2. `FinancialReportService::adSpendByCountry`: `SUM(subscription_charged + total_charged)` so revenue does not drop to 0 for fixed bookings.
3. `AdAttributionService::forBooking`: `ad_spend = total_spend`; ROI formula unchanged.
4. `AdBookingResource`: keep `quoted_amount`, `total_charged`; ADD `subscription_charged`, `total_spend`, `clicks_delivered`, `impressions_delivered`, `effective_cpc`, `effective_cpm`. Check `postman/` and mobile/partner apps (`partner_app`, `frontend`) for consumers of these fields and list them (do not edit apps unless a field was renamed — nothing is renamed).
5. Admin and partner booking detail pages: show separate rows "Subscription fee" (`subscription_charged`) and "Usage spend" (`total_charged`, only for CPM/CPC) plus "Total spend"; add `admin.paid_ad_bookings.*` and partner keys to BOTH lang/en and lang/ar. All amounts via `number_format($x)` in base currency — NO `/100`.
6. Notifications `AdBookingCompletedNotification` / `AdBookingCancelledNotification` already lost their `/100`; make Completed use `total_spend` for the "spend" text.

## PROMPT D — Tests + verification (owns: backend/tests/Feature/Ads/*)
Update existing tests that set `total_charged` / assume old meaning (`AdBookingServiceTest` ~225-254, plus factories/inline `PaidAdBooking::create` in ProductDerivedCreativeTest, AdPopupControllerTest, ListingBoostServiceTest, ListingBoostRankingTest, NawiAdsLifecycleTest). Add tests:
1. Fixed booking paid via wallet: `subscription_charged == quoted_amount`, `total_charged == 0`.
2. **Regression:** active fixed booking receives 1 click and 1,000 impressions -> still `active`, `total_charged == 0`, counters incremented, no `paid_ad_charges` cpc/cpm rows.
3. CPC: rate 100, budget 500 -> 5 clicks complete it as budget_exhausted, `total_charged == 500`, unspent refund 0; 3 clicks + cancel refunds 200.
4. CPM: 1,000-impression blocks charge `unit_rate`, capped at budget.
5. Offline payment sets `subscription_charged` not `total_charged`.
6. Backfill migration test/manual check on a fixed row and a CPC row.
7. `effective_cpc/effective_cpm` null when denominators are 0.
8. `FinancialReportService::adSpendByCountry` includes fixed fees.
Run `php artisan test --filter=Ads` and report exact pass/fail output; if anything fails, say so — do not claim green without running.

## Manual follow-up (not for agents)
- Reactivate `ADB-20260919-ILWT3` on the environment that actually holds it (it does not exist in `marketplace_platform_live`): `UPDATE paid_ad_bookings SET status='active', completed_at=NULL WHERE id='01a0ba54-836a-73ea-a845-f65ee5cffe19';` then bust `paid_ads:active:{country_id}`. Run this AFTER deploying Prompt A's backfill so its row is normalized (`subscription_charged = 100`, `total_charged = 0`).
- Other fixed bookings that received a click/impression before the guard may also have been completed early — list `completed` fixed bookings whose `completed_at < booked_until` and review.
