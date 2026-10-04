# Marketer Campaign — Complete Flow, Gaps & Fix Prompts

> Audited: 2026-10-04  
> Branch: main  
> Author: dev.youssefmagdy@gmail.com

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Actors & Their Roles](#2-actors--their-roles)
3. [Full Campaign Lifecycle](#3-full-campaign-lifecycle)
4. [Commission & Calculation Model](#4-commission--calculation-model)
5. [Rejection-Replacement Logic](#5-rejection-replacement-logic)
6. [Database Schema Map](#6-database-schema-map)
7. [Existing Endpoints Inventory](#7-existing-endpoints-inventory)
8. [Identified Gaps](#8-identified-gaps)
9. [Fix Prompts (one per gap)](#9-fix-prompts-one-per-gap)

---

## 1. System Overview

The Marketer Campaign system is an **affiliate/influencer program** where:

- A **vendor** (or admin) creates a campaign tied to a product listing and invites specific marketers.
- **Marketers** receive, accept or reject the invitation.
- On rejection or timeout the platform auto-picks a replacement marketer.
- Accepted marketers get a unique referral link and a public **marketer listing** page.
- Customer purchases through that link generate **conversion records** and **commission payouts** to the marketer.

This is separate from:
- **`AdCampaign`** — vendor-only keyword/sponsored-product campaigns (no marketer involvement).
- **`PaidAdBooking`** — paid banner slots (both vendors and marketers, but no referral/commission system).

---

## 2. Actors & Their Roles

| Actor | Key Actions |
|---|---|
| **Vendor** | Create campaign, select listing, set commission, invite marketers, cancel (pending only), approve marketer-originated requests |
| **Admin** | Approve / reject any campaign, set/update commission before approval, invite additional marketers, mark platform fees paid, update sample status |
| **Marketer** | View invitations, accept (pays influencer fee from wallet), reject (triggers replacement), view active/finished campaigns, originate campaign requests |
| **System (jobs)** | Auto-approve campaign on timeout, timeout invitation → trigger replacement, monitor stock, approve conversions on delivery, release pending balance after clearing days |

---

## 3. Full Campaign Lifecycle

```
                        ┌─────────────────────────────────────────────────────────────┐
                        │                   CAMPAIGN CREATION                         │
                        └─────────────────────────────────────────────────────────────┘

  VENDOR                          ADMIN                          SYSTEM
  ──────                          ─────                          ──────
  POST /partner/marketer-campaigns
  (vendor_listing_id, commission,
   requested_marketer_vendor_ids)
         │
         ▼
  MarketerCampaign.status = pending_admin
  ProcessCampaignAutoApproveJob dispatched
  (delay = admin_review_window_hours)
         │
         ├─── Admin reviews ──────────────────────►  POST /{campaign}/approve
         │                                                    │
         │         (or auto-approved by job) ◄───────────────┘
         │
         ▼
  MarketerCampaign.status = active | auto_approved
  ProcessInvitationTimeoutJob dispatched per marketer
  SendCampaignWhatsAppNotificationJob dispatched per marketer
  marketer_campaign_invitations rows created (status = pending)
  CampaignApprovedNotificationJob dispatched

                        ┌─────────────────────────────────────────────────────────────┐
                        │                 INVITATION HANDLING                         │
                        └─────────────────────────────────────────────────────────────┘

  MARKETER
  ────────
  POST /marketer/invitations/{inv}/accept
         │
         ├── influencer? → deduct platform_fee from marketer wallet
         ├── create MarketerListing (status = active)
         ├── generate referral_code + referral_link + QR
         └── notify vendor admins

  POST /marketer/invitations/{inv}/reject
         │
         └── MarketerCampaignService::rejectInvitation()
                   │
                   └── replaceMarketer() → [see Section 5]

  ProcessInvitationTimeoutJob fires (acceptance_window_hours elapsed)
         │
         └── MarketerCampaignService::handleInvitationTimeout()
                   │
                   └── replaceMarketer() → [see Section 5]

                        ┌─────────────────────────────────────────────────────────────┐
                        │                  ACTIVE CAMPAIGN FLOW                       │
                        └─────────────────────────────────────────────────────────────┘

  CUSTOMER
  ────────
  Visits referral_link → LastClickAttributionService::recordClick()
         (stored in cache, TTL = marketer_attribution_window_days × 86400)
         │
  Adds to cart / purchases
         │
         ▼
  Order placed → LastClickAttributionService::resolveAndRecordConversion()
         │
         ├── Priority 1: marketer listing cart item (direct)
         ├── Priority 2: last-click referral from cache
         └── Priority 3: no attribution
         │
         ▼
  MarketerCampaignConversion created (status = pending)
  commission_amount calculated [see Section 4]
  flash_sale_bonus_amount added if applicable

  ApproveMarketerConversionsJob (daily)
         │ after order delivered + return window passed
         ▼
  MarketerCampaignConversion.status = approved
  marketer.pending_balance += commission_amount

  ReleaseMarketerPendingCommissionJob (daily)
         │ after marketer_payout_clearing_days elapsed
         ▼
  MarketerCampaignConversion.status = wallet_released
  marketer.pending_balance -= commission_amount
  marketer.balance += commission_amount

                        ┌─────────────────────────────────────────────────────────────┐
                        │                   CAMPAIGN COMPLETION                       │
                        └─────────────────────────────────────────────────────────────┘

  MonitorCampaignStockJob (hourly)
         │
         ├── low stock → pause campaign + all marketer listings
         ├── restocked → resume campaign + all marketer listings
         └── out of stock + budget exhausted → markCampaignDone()
                   │
                   ├── cancel pending invitations
                   ├── archive all marketer listings
                   └── notify vendor + marketers
```

---

## 4. Commission & Calculation Model

### Commission Types

| Type | How it works |
|---|---|
| `fixed` | Flat amount per item (stored in `marketer_commission_amount`) |
| `percentage` | % of item price |
| `last_click` | Same as fixed but only credits the last-click referral |
| `tiered` | Amount depends on the marketer's cumulative `sale_number_in_campaign`, resolved from `marketer_campaign_tiered_rules` |

### Split: marketer vs platform

Both amounts are set **at campaign creation** (or auto-approve resolution):

```
Campaign record
├── marketer_commission_amount  → paid to marketer per conversion
└── platform_commission_amount  → platform's cut (metadata only; not credited to any wallet in current code — see Gap #7)
```

### Auto-approve resolution path

```
resolveDefaultCommission()
  └── MarketerCommissionCountrySetting (country + marketer_type)
        ├── affiliate_commission_amount → marketer_commission_amount
        └── CommissionRuleResolver::resolve() → platform_commission_amount
```

### Per-conversion calculation (`LastClickAttributionService::recordConversion`)

```
base = marketer_commission_amount × quantity
     │
     ├── tiered? → lookup MarketerCampaignTieredRule where from_sale_number <= current_sale_count
     ├── no tiered → use campaign.marketer_commission_amount
     │             → fallback: MarketerCommissionCountrySetting
     │             → fallback: MarketerCommissionRateService::calculateCommissionAmount()
     │
     ├── flash_sale_bonus: + FlashSaleMarketerInvitation bonus if live flash sale
     └── discount: MarketerProfile::applyCommissionDiscount() reduces total
```

### Wallet flow (in integers, base currency)

```
Conversion approved  → marketer.pending_balance  += commission_amount
Clearing days pass   → marketer.pending_balance  -= commission_amount
                        marketer.balance          += commission_amount
```

---

## 5. Rejection-Replacement Logic

**Implemented in** `MarketerCampaignService::replaceMarketer()` (line 992).

Triggered by:
- `rejectInvitation()` (marketer explicitly rejects)
- `handleInvitationTimeout()` (invitation window expires)

### Replacement algorithm

```
1. Collect all already-invited marketer IDs for this campaign
2. Query marketers WHERE:
   - global_status = active
   - id NOT IN already_invited_ids
   - marketer_type matches old marketer's type (influencer/affiliate)
   - accepted_campaigns_count >= marketer_replacement_min_accepted_campaigns (setting, default 0)
3. ORDER BY accepted_campaigns_count ASC   ← gives chance to less-active marketers first
4. Take first result as replacement
5. dispatchInvitation(campaign, replacement.id)
6. new_invitation.replaced_invitation_id = old_invitation.id   ← links the chain
7. Notify vendor admins:
   - CampaignInvitationRejectedNotification
   - MarketerReplacedNotification (if replacement found)
```

> **Note:** If no replacement is found the campaign continues with remaining accepted marketers. The vendor is notified but no action is taken automatically.

---

## 6. Database Schema Map

```
vendors
  └──(vendor_id)──► marketer_campaigns ◄──(requested_by_marketer_id)── marketers
                          │
                          │(campaign_id)
                          ▼
              marketer_campaign_invitations
              ├── marketer_id → marketers
              ├── replaced_invitation_id → self (chain)
              ├── referral_code / referral_link / qr_code_path
              ├── platform_fee_amount / platform_fee_status
              └── total_commission_earned / total_conversions
                          │
              ┌───────────┴───────────┐
              ▼                       ▼
      marketer_listings   marketer_campaign_conversions
      ├── marketer_id         ├── campaign_id
      ├── product_variant_id  ├── invitation_id
      ├── invitation_id       ├── order_id / order_item_id
      ├── referral_code       ├── commission_amount
      └── status              ├── flash_sale_bonus_amount
                              ├── status (pending→approved→wallet_released)
                              └── tiered_rule_id
```

---

## 7. Existing Endpoints Inventory

### Admin (`/admin/marketer-campaigns`)

| Method | Path | Handler | Status |
|---|---|---|---|
| GET | `/` | index | ✅ |
| GET | `/financials` | financials | ✅ |
| GET | `/search-listings` | searchVendorListings | ✅ |
| GET | `/create` | create form | ✅ |
| POST | `/` | store | ✅ |
| GET | `/{campaign}` | show | ✅ |
| POST | `/{campaign}/approve` | approve | ✅ |
| POST | `/{campaign}/reject` | reject | ✅ |
| POST | `/{campaign}/invite-marketers` | inviteMarketers | ✅ |
| PATCH | `/{campaign}/samples/{sample}` | updateSampleStatus | ✅ |
| PATCH | `/{campaign}/invitations/{inv}/mark-fee-paid` | markInvitationFeePaid | ✅ |
| POST | `/{campaign}/category-rules/sync` | syncCategoryRules | ✅ |
| **PATCH** | **`/{campaign}/commission`** | **updateCommission** | ❌ MISSING |

### Vendor / Partner (`/partner/marketer-campaigns`)

| Method | Path | Handler | Status |
|---|---|---|---|
| GET | `/` | index | ✅ |
| GET | `/create/{vendorListing}` | create form | ✅ |
| POST | `/` | store | ✅ |
| GET | `/{campaign}` | show | ✅ |
| POST | `/{campaign}/cancel` | cancel | ✅ (pending_admin only) |
| POST | `/{campaign}/invite-marketers` | inviteMarketers | ✅ |
| POST | `/{campaign}/samples/{sample}/custom-attributes` | saveSampleCustomAttributes | ✅ |
| GET | `/campaigns/search-marketers` | searchMarketers | ✅ |
| **POST** | **`/{campaign}/approve-request`** | **approveMarketerRequest** | ❌ MISSING |
| **POST** | **`/{campaign}/cancel-active`** | **cancel active campaign** | ❌ MISSING |

### Marketer Web (`/marketer`)

| Method | Path | Handler | Status |
|---|---|---|---|
| GET | `/invitations` | index | ✅ |
| POST | `/invitations/{inv}/accept` | accept | ✅ |
| POST | `/invitations/{inv}/reject` | reject | ✅ |
| GET | `/campaigns/active` | active | ✅ |
| GET | `/campaigns/finished` | finished | ✅ |
| **POST** | **`/campaigns/request`** | **requestCampaign** | ❌ MISSING |

### Marketer API (`/api/marketer`)

| Method | Path | Handler | Status |
|---|---|---|---|
| GET | `/invitations` | index | ✅ |
| POST | `/invitations/{inv}/accept` | accept | ✅ |
| POST | `/invitations/{inv}/reject` | reject | ✅ |
| GET | `/campaigns/active` | active | ✅ |
| GET | `/campaigns/finished` | finished | ✅ |
| **GET** | **`/invitations/{inv}`** | **show single invitation** | ❌ MISSING |
| **POST** | **`/campaigns/request`** | **requestCampaign** | ❌ MISSING |

---

## 8. Identified Gaps

### 🔴 Critical (blocks core flow)

| # | Gap | File / Location |
|---|---|---|
| G1 | Admin cannot update `marketer_commission_amount` / `platform_commission_amount` on a pending campaign. If vendor submitted with 0, `approveCampaign()` throws and the campaign is stuck. | `Admin/MarketerCampaignController.php` — no `updateCommission` route/method |
| G2 | `approveMarketerRequest()` service method exists but is unreachable — no route wires vendor/admin to it. Marketer-originated campaigns can never be approved. | `MarketerCampaignService::approveMarketerRequest()` line 291; `routes/partner.php` lines 466–478 |
| G3 | No endpoint for marketer to request/originate a campaign. `requestCampaign()` service method exists but no route. | `MarketerCampaignService::requestCampaign()` line 277; `routes/marketer.php` |

### 🟠 Moderate (incomplete flows)

| # | Gap | File / Location |
|---|---|---|
| G4 | Rejected marketer is **not notified**. Only vendor admins receive `CampaignInvitationRejectedNotification`. | `MarketerCampaignService::replaceMarketer()` line 1011 |
| G5 | `markConversionsPaid($conversionIds, $payoutId)` accepts a `$payoutId` but never persists it. No FK from conversion → payout record. | `MarketerCampaignService::markConversionsPaid()` line 61 |
| G6 | `platform_commission_amount` is stored on the campaign but **never credited** to a platform wallet/ledger during conversion recording. It is informational only. | `LastClickAttributionService::recordConversion()` |
| G7 | Vendor can only cancel a campaign in `pending_admin` status. Cannot cancel an active campaign. | `Partner/MarketerCampaignController.php` line 231 |

### 🟡 Minor (UX / completeness)

| # | Gap | File / Location |
|---|---|---|
| G8 | No marketer API endpoint for a single invitation (`GET /api/marketer/invitations/{inv}`). | `Api/Marketer/InvitationController.php` |
| G9 | No vendor API for any marketer campaign management (all vendor-side is web-only). | `routes/api_vendor.php` |
| G10 | Replacement algorithm sorts by `accepted_campaigns_count ASC` but the setting name says "minimum" — intention says give chance to less active marketers, but code currently orders DESC. | `MarketerCampaignService::replaceMarketer()` line 1002 |

---

## 9. Fix Prompts (one per gap)

Use each prompt as a standalone task for an AI coding agent or developer.

---

### Fix G1 — Admin can update commission before approving

```
Context:
- File: backend/app/Http/Controllers/Admin/MarketerCampaignController.php
- File: backend/routes/admin.php (marketer-campaigns group, lines 1082–1099)
- Service method: MarketerCampaignService::approveCampaign() throws if marketer_commission_amount = 0

Task:
Add PATCH /admin/marketer-campaigns/{campaign}/commission endpoint.
1. Add route in routes/admin.php inside the marketer-campaigns group:
   Route::patch('/{marketerCampaign}/commission', [MarketerCampaignController::class, 'updateCommission'])
        ->middleware('can:marketer_campaigns.approve');
2. Add updateCommission(Request $request, MarketerCampaign $campaign) to Admin/MarketerCampaignController:
   - Validate: marketer_commission_amount (required, integer, min:1), platform_commission_amount (required, integer, min:0)
   - Guard: abort_unless($campaign->status === 'pending_admin', 422, 'Campaign is not pending admin review')
   - Update both fields on the campaign and save
   - Return JSON success response
3. No service method needed — this is a simple field update with a status guard.
```

---

### Fix G2 — Vendor/Admin can approve a marketer-originated request

```
Context:
- Service method: MarketerCampaignService::approveMarketerRequest(MarketerCampaign $campaign, array $commission)
  exists at line 291 of backend/app/Services/MarketerCampaignService.php
- The campaign will have status = 'marketer_requested' and requested_by_marketer_id set
- File: backend/app/Http/Controllers/Partner/MarketerCampaignController.php
- File: backend/routes/partner.php (marketer-campaigns group, lines 466–478)

Task:
1. Add route in routes/partner.php:
   Route::post('/{marketerCampaign}/approve-request', [MarketerCampaignController::class, 'approveRequest'])
        ->middleware('can:marketer_campaigns.create');
2. Add approveRequest(Request $request, MarketerCampaign $campaign) to Partner/MarketerCampaignController:
   - Validate: marketer_commission_amount (required, integer, min:1),
               platform_commission_amount (required, integer, min:0)
   - Guard: abort_unless($campaign->status === 'marketer_requested', 422)
   - Guard: abort_unless($campaign->vendor_id === auth()->user()->vendor->id, 403)
   - Call: $this->campaignService->approveMarketerRequest($campaign, $request->only([...]))
   - Redirect back with success flash
3. Also add the same route to admin.php so admins can approve marketer-requested campaigns:
   Route::post('/{marketerCampaign}/approve-request', [MarketerCampaignController::class, 'approveRequest'])
        ->middleware('can:marketer_campaigns.approve');
```

---

### Fix G3 — Marketer can request/originate a campaign

```
Context:
- Service method: MarketerCampaignService::requestCampaign(Marketer $marketer, CampaignSource $source, array $data)
  exists at line 277 of backend/app/Services/MarketerCampaignService.php
- File: backend/app/Http/Controllers/Marketer/CampaignController.php
- File: backend/routes/marketer.php (campaigns section)
- File: backend/app/Http/Controllers/Api/Marketer/CampaignController.php
- File: backend/routes/api_marketer.php

Task:
1. Add web route in routes/marketer.php:
   Route::post('/campaigns/request', [CampaignController::class, 'request']);
2. Add request(Request $request) to Marketer/CampaignController:
   - Validate: listing_id (required, uuid), listing_type (required, in:vendor_listing,admin_listing),
               notes (nullable, string, max:1000)
   - Resolve the CampaignSource from listing_type + listing_id
   - Call: $this->campaignService->requestCampaign(auth()->user()->marketer, $source, $validated)
   - Redirect to active campaigns with success flash
3. Add API route in routes/api_marketer.php:
   Route::post('/campaigns/request', [Api\Marketer\CampaignController::class, 'request']);
4. Add request(Request $request) to Api/Marketer/CampaignController — same logic, return JSON.
```

---

### Fix G4 — Notify rejected marketer

```
Context:
- File: backend/app/Services/MarketerCampaignService.php, method replaceMarketer() at line ~1011
- The method notifies vendor admins of rejection but does NOT notify the rejected marketer themselves
- Notification class may already exist: app/Notifications/Marketer/CampaignInvitationRejectedNotification.php
  (check if it exists; if not, create it)

Task:
1. In replaceMarketer(), after updating oldInvitation status and before notifying vendor admins,
   add notification to the marketer:
   $oldInvitation->marketer->notify(new \App\Notifications\Marketer\CampaignInvitationRejectedNotification($oldInvitation));
2. If the notification class does not exist, create it:
   - Extends Notification, implements ShouldQueue
   - via: ['database', 'mail'] (or match the pattern of other marketer notifications in the project)
   - Payload: campaign title, listing name, rejection reason if any
3. Also notify on timeout (handleInvitationTimeout calls replaceMarketer, so the above covers it).
```

---

### Fix G5 — Persist payout ID on conversion when paid

```
Context:
- File: backend/app/Services/MarketerCampaignService.php, method markConversionsPaid() line 61
- The method accepts $payoutId but never writes it to any field
- Table: marketer_campaign_conversions — check if payout_id column exists; likely it does not

Task:
1. Create migration: add nullable uuid column `payout_id` to marketer_campaign_conversions table.
   Schema::table('marketer_campaign_conversions', function (Blueprint $table) {
       $table->uuid('payout_id')->nullable()->after('wallet_released_at');
       $table->foreign('payout_id')->references('id')->on('marketer_payouts'); // adjust table name
   });
2. In markConversionsPaid(), after updating each conversion's paid status, set:
   MarketerCampaignConversion::whereIn('id', $conversionIds)
       ->update(['payout_id' => $payoutId, 'paid_at' => now()]);
3. Add payout_id to the model's $fillable array.
4. Verify marketer_payouts (or equivalent) table name by checking existing payout models/migrations.
```

---

### Fix G6 — Credit platform commission to platform ledger on conversion

```
Context:
- File: backend/app/Services/LastClickAttributionService.php, method recordConversion()
- campaign->platform_commission_amount is stored but never credited anywhere
- Understand the platform's financial ledger system first:
  grep -r "platform.*ledger\|platform.*wallet\|platform.*revenue\|platform.*earning" backend/app/Models/
  grep -r "PlatformRevenue\|PlatformLedger\|PlatformWallet" backend/app/

Task:
1. After identifying the platform revenue model/table, in recordConversion() after creating
   MarketerCampaignConversion, record a platform revenue entry:
   PlatformRevenue::create([
       'source_type' => 'marketer_campaign_conversion',
       'source_id'   => $conversion->id,
       'amount'      => $campaign->platform_commission_amount * $quantity,
       'currency'    => $campaign->currency,
       'vendor_id'   => $campaign->vendor_id,
   ]);
2. If no platform revenue model exists, create it with a migration before implementing step 1.
3. If the platform uses a different revenue tracking pattern (e.g., order-level deductions),
   match that pattern instead.
```

---

### Fix G7 — Vendor can cancel an active campaign

```
Context:
- File: backend/app/Http/Controllers/Partner/MarketerCampaignController.php, cancel() method
- Currently: abort_unless($campaign->status === 'pending_admin', 403)
- Service method: MarketerCampaignService::cancelCampaign() — already handles active campaigns
  (cancels invitations + archives listings + notifies)

Task:
1. Add a separate route in routes/partner.php:
   Route::post('/{marketerCampaign}/cancel-active', [MarketerCampaignController::class, 'cancelActive'])
        ->middleware('can:marketer_campaigns.cancel');
2. Add cancelActive(MarketerCampaign $campaign) method:
   - Guard: abort_unless(in_array($campaign->status, ['active', 'auto_approved', 'paused']), 422)
   - Guard: abort_unless($campaign->vendor_id === auth()->user()->vendor->id, 403)
   - Require a cancellation reason: validate reason (required, string, min:10)
   - Call: $this->campaignService->cancelCampaign($campaign)
   - Redirect back with success flash
3. Keep the existing cancel() method (pending_admin only) unchanged.
```

---

### Fix G8 — Marketer API: show single invitation

```
Context:
- File: backend/app/Http/Controllers/Api/Marketer/InvitationController.php
- File: backend/routes/api_marketer.php (invitations group)

Task:
1. Add route: Route::get('/invitations/{invitation}', [InvitationController::class, 'show']);
2. Add show(MarketerCampaignInvitation $invitation) method:
   - Policy check: abort_unless($invitation->marketer_id === auth()->user()->marketer->id, 403)
   - Load relations: campaign.vendorListing, marketer, replacedInvitation
   - Return InvitationResource (or plain resource array matching existing index response shape)
```

---

### Fix G10 — Replacement algorithm sort direction

```
Context:
- File: backend/app/Services/MarketerCampaignService.php, replaceMarketer() at line ~1002
- Current behaviour: ORDER BY accepted_campaigns_count DESC — picks the MOST active marketer
- Intended behaviour (from column name and business logic): give a chance to LESS active marketers
  → ORDER BY accepted_campaigns_count ASC

Task:
1. In replaceMarketer(), find the query that orders marketers for replacement.
   Change: ->orderBy('accepted_campaigns_count', 'desc')
   To:     ->orderBy('accepted_campaigns_count', 'asc')
2. Verify the column name used in the query matches the actual column on the marketers table
   (may be a computed/counter_cache column — grep for 'accepted_campaigns_count' in migrations).
3. Add a test case: given two candidate marketers (one with 0 accepted campaigns, one with 5),
   verify the replacement picks the one with 0.
```

---

## Implementation Order (recommended)

| Priority | Fixes | Reason |
|---|---|---|
| 1 | G1 (admin update commission) | Blocks approve flow when vendor submits 0 commission |
| 2 | G10 (sort direction) | One-line fix, prevents wrong behavior from day one |
| 3 | G4 (notify rejected marketer) | Basic UX, low effort |
| 4 | G2 + G3 (marketer request flow) | Complete the bidirectional origination flow |
| 5 | G7 (vendor cancel active) | Vendor control |
| 6 | G8 (API single invitation) | API completeness |
| 7 | G5 (payout ID persistence) | Financial audit trail |
| 8 | G6 (platform commission ledger) | Revenue tracking — needs platform ledger design first |
