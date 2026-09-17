# Gift Cards & Voucher System — Enhancement Plan

Analysis date: 2026-09-18
Scope: (1) fix broken gift-card checkout, (2) audit full gift-card/voucher lifecycle, (3) make the `/gift-cards` landing page admin-configurable (banners + FAQs), (4) resolve the dead email-verification flow.

Each section below is a standalone, copy-pasteable prompt for a sub-agent. Give each sub-agent only its own section — they touch different files and can mostly run in parallel, except where noted.

---

## 0. Root cause summary (read this first)

**Bug:** "The country payment gateway id field is required." on gift card checkout.

- The gift-card purchase form (`frontend/src/features/noon/gift-cards/view/components/gift-card-form.tsx:64-83`) submits `country_payment_gateway_id: ""` — there is no payment-method selector in the UI at all (a `// TODO` comment in the code admits this).
- The backend (`backend/app/Http/Requests/Api/Customer/PurchaseGiftCardRequest.php:14-24`) requires this field as a valid `country_payment_gateways` UUID.
- Regular checkout already has the pattern to copy: `GET /checkout/payment-options` (`backend/app/Http/Controllers/Api/Customer/CheckoutController.php:56-105`) returns the active gateways for the customer's country, and the UI lets them pick one before placing the order.
- Important wrinkle: `GiftCardPurchaseService::purchase()` (`backend/app/Services/GiftCardPurchaseService.php:39-131`) currently never actually charges the selected gateway — it looks the gateway up only to label the order, then marks the order `completed` immediately. So today "payment" is a no-op. The fix must decide whether to (a) wire real payment gateway charging now, or (b) just fix the immediate 400 error by adding the selector and keep payment as a later milestone. Section 1 below assumes (b) as the fast fix, with (a) called out as a distinct follow-up in Section 2.

---

## 1. PROMPT — Fix "country payment gateway id required" checkout error

```
Fix a broken gift-card checkout flow in this Laravel + Next.js marketplace app.

BUG: On the gift-card purchase page, clicking "Proceed to buy" always fails with
"The country payment gateway id field is required." The frontend form never
collects a payment method and submits country_payment_gateway_id: "".

Root cause files:
- frontend/src/features/noon/gift-cards/view/components/gift-card-form.tsx:64-83
  (submits country_payment_gateway_id: "" — has a TODO comment admitting no
  payment-method selector exists)
- frontend/src/features/noon/gift-cards/api/gift-cards.actions.ts:53-64
  (purchaseGiftCard() just posts the payload, no gateway list is fetched first)
- backend/app/Http/Requests/Api/Customer/PurchaseGiftCardRequest.php:14-24
  (the validation rule requiring country_payment_gateway_id as a valid
  country_payment_gateways UUID)

REFERENCE PATTERN (copy this, don't invent a new one):
- backend/app/Http/Controllers/Api/Customer/CheckoutController.php:56-105 exposes
  GET /checkout/payment-options, returning active CountryPaymentGateway rows for
  the customer's country (id, code, display name, fees). This is the exact shape
  a gift-card payment-method selector should consume.
- backend/app/Models/CountryPaymentGateway.php — belongsTo Country + PaymentGateway,
  has an active() scope and forCountry() scope already.

TASKS:
1. Backend: add a route + thin controller method (or reuse /checkout/payment-options
   if it's generic enough — check whether it depends on cart/checkout session state
   that a gift-card purchase wouldn't have) that returns active payment gateways for
   the current customer's country, usable from the gift-cards page. Keep it a GET,
   no side effects.
2. Frontend: in gift-card-form.tsx, add a PaymentMethodSelector component (follow the
   existing pattern of AmountSelector/QuantitySelector in the same directory — same
   styling conventions, same form-state approach). Fetch the gateway list on mount
   (or when the customer's country is known), render radio/card options, store the
   selected id in form state, and submit that as country_payment_gateway_id instead
   of "".
3. Remove the TODO comment once wired.
4. Handle the empty-list edge case (no active gateways for a country) with a clear
   inline error instead of letting the form submit.
5. Do NOT attempt to implement real payment gateway charging in
   GiftCardPurchaseService — that is out of scope for this task and covered
   separately. Just make the field populate correctly so checkout succeeds and the
   order is created as it is today.

TEST: manually purchase a gift card end-to-end after the fix (select amount →
select payment method → proceed to buy → confirm order created, no validation
error). Add/update any existing feature test around
CustomerGiftCardStoreController::purchase if one exists (search
backend/tests for GiftCard).
```

---

## 2. PROMPT — Audit & harden the full gift card/voucher lifecycle

```
Audit the entire gift card / voucher lifecycle in this Laravel marketplace app,
from purchase through redemption through expiry, and fix concrete correctness
gaps you find. This is NOT a rewrite — targeted fixes only, and confirm each one
with a test before moving to the next.

SYSTEM MAP (already researched, use as your starting point — verify against
current code since it may have changed):

Purchase path:
- backend/app/Http/Controllers/Api/Customer/CustomerGiftCardStoreController.php
  (purchase())
- backend/app/Services/GiftCardPurchaseService.php (purchase(), deliverCard(),
  getPurchaseHistory())
- backend/app/Models/GiftCardBatch.php, GiftCard.php, GiftCardPurchase.php,
  GiftCardTransaction.php
- Jobs: SendGiftCardNotificationJob, SendGiftCardDeliveryJob

Redemption path:
- backend/app/Services/GiftCardService.php (redeemToWallet(), refundToCard(),
  adminAdjust(), expireDueCards())
- backend/app/Http/Controllers/Api/Customer/GiftCardController.php (validate, mine)
- Wallet redemption: routes/api_customer_v1.php ~570-582 → CustomerWalletController
- IMPORTANT ARCHITECTURE NOTE (see comment at top of GiftCard.php): redeeming a
  gift card credits its full remaining_balance to the customer's wallet
  (CustomerWallet) — gift cards do NOT attach to orders directly. Spending later
  happens via wallet balance at checkout. Confirm this is still accurate before
  assuming otherwise.

Admin management:
- backend/app/Http/Controllers/Admin/AdminGiftCardController.php (batch CRUD,
  activateBatch, downloadPins, activateCard, adjustBalance, blockCard,
  resendDelivery, expireStale)
- backend/routes/admin.php ~742-761

Expiry:
- backend/app/Console/Commands/ExpireGiftCards.php + gift-cards:expire scheduled
  daily (backend/routes/console.php ~75-80) → GiftCardService::expireDueCards()

SPECIFIC GAPS TO INVESTIGATE AND FIX:

1. PAYMENT IS A NO-OP: GiftCardPurchaseService::purchase() looks up the chosen
   CountryPaymentGateway only to label the order (line ~89), then immediately
   marks the order `completed`/`payment_status: pending` without actually
   charging anything. Decide with the team whether:
   (a) this is intentional for now (gift cards settle via a manual/offline
       process) — if so, payment_status should probably reflect that honestly
       (e.g. stay 'pending' until an admin or webhook confirms payment, not be
       silently treated as paid), or
   (b) real gateway charging needs to be wired now.
   Do not silently leave an order marked "completed" with unconfirmed payment —
   at minimum this is a data-integrity/fraud risk worth flagging explicitly in
   your PR description even if you decide full gateway integration is out of
   scope.

2. PURCHASED-CARD DELIVERY / PIN GAP: GiftCardPurchaseService::deliverCard()
   (~136-151) has a comment noting the plain PIN isn't stored after batch
   generation, so purchased-card delivery either needs a fresh PIN generated at
   purchase time or must go code-only. Check GiftCardService::generateBatch() —
   PINs are hashed immediately (pin_hash only). Determine: when a customer buys a
   card from CustomerGiftCardStoreController::purchase(), does the recipient
   email actually receive a usable PIN? Trace SendGiftCardNotificationJob →
   GiftCardPurchasedMail and confirm what's in the email body. Fix so redemption
   is actually possible after purchase (generate+deliver a real PIN, or redesign
   the flow to not require one for purchased-then-redeemed cards).

3. GiftCardDeliveryMail is referenced in a comment as "not yet wired"
   (Mail::to(...)->send(new GiftCardDeliveryMail(...)) is commented out in
   deliverCard()). Confirm current behavior — is delivery mail actually sent
   anywhere today, or is SendGiftCardDeliveryJob dispatched but a no-op? Wire it
   if missing.

4. "VALID FOR 1 YEAR" CLAIM: the storefront UI states gift cards are valid for 1
   year, but expires_at is set per-batch by an admin with no default/enforcement
   in GiftCardService::generateBatch() or the admin batch-create request. Either:
   (a) default expires_at to purchase_date + 1 year when an admin leaves it blank
       (check StoreBatchRequest for gift-card-batches, in the same directory as
       AdjustGiftCardBalanceRequest), or
   (b) if admins intentionally set arbitrary expiry per batch, fix the storefront
       FAQ copy to not claim a fixed "1 year" (tie it to Section 3's dynamic FAQ
       work instead of hardcoding a false claim).
   Pick (a) unless you find evidence batches are meant to have varying expiry
   windows for business reasons — check with existing batches in a DB dump/seeder
   if available.

5. EXPIRY TRANSACTION CORRECTNESS: expireDueCards() logs an `expiry`
   GiftCardTransaction with a negative amount equal to full remaining_balance
   when a card lapses. Confirm this can't run twice on the same card (idempotency
   — e.g. does it filter status='active' before transitioning, preventing a
   double-expiry transaction on a re-run of the scheduled command?).

6. GIFT CARD ↔ ORDER LINKAGE FOR PURCHASE REFUNDS: if a gift-card purchase order
   is refunded (customer disputes charge, or order cancelled), is there a path
   that reverses/blocks the underlying GiftCard (e.g. mark it blocked before
   it's redeemed)? Check AdminGiftCardController::blockCard and whether it's ever
   triggered automatically by order refund/cancellation, or only manually by an
   admin. If only manual, flag as a gap (a refunded gift card purchase could
   still be redeemed for free) but don't necessarily auto-build the integration
   unless it's a quick, safe win — otherwise document it as a known gap in your
   PR description.

7. TESTS: search backend/tests for existing GiftCard*Test coverage. Add tests for
   whichever of the above you change. At minimum, add a feature test asserting
   the full happy path: purchase → wallet redemption → balance reflected → cannot
   redeem twice.

Report back a clear list of what was fixed vs. what was investigated-and-flagged-
but-intentionally-left, with reasoning for each deferred item.
```

---

## 3. PROMPT — Make the gift-cards landing page dynamic (banners + FAQs)

```
The gift-cards landing page (frontend route:
frontend/app/[locale]/(noon)/(pages-with-footer)/gift-cards/page.tsx, rendered
component frontend/src/features/noon/gift-cards/list/index.tsx) is currently
100% hardcoded: two banner images referenced by literal file path
(frontend/public/images/banner_image_web_url_ae-en_v2.avif and
desktop_redeem_banner_en.avif) and a FAQ section
(frontend/src/features/noon/gift-cards/list/components/faq-section.tsx:13-17)
built from a hardcoded array of i18n translation keys. The "View all FAQs" and
"Read Terms & Conditions" links both point to href="/" — dead links.

GOAL: make banners and FAQs on this page admin-manageable, following existing
CMS patterns already in this codebase rather than inventing new ones.

EXISTING PATTERNS TO REUSE:

1. Banners — backend/app/Models/Banner.php + `banners` table. Has
   placement_code (enum: homepage_hero, homepage_secondary_left/right,
   homepage_midpage, category_top_{slug}, category_sidebar, search_top,
   cart_banner, checkout_banner, product_page_bottom, app_splash, app_home_top,
   email_header), country_id scoping, starts_at/ends_at scheduling, device/
   audience targeting, bilingual CTA fields, status, click/impression tracking.
   Managed via backend/app/Http/Controllers/Admin/BannerController.php, served
   via backend/app/Services/BannerService.php,
   backend/app/Jobs/BannerSchedulerJob.php (auto activates/expires scheduled
   banners).
   NOTE: no customer-facing JSON API currently exposes Banner data — it's only
   rendered server-side into Blade pages via PageRendererService. The Next.js
   frontend has no precedent for fetching banners. You will need to add one.

2. FAQs — backend/app/Models/Faq.php. Has a `context` field (CONTEXTS const,
   currently ['seller','product_ads','display_ads']) scoping which page consumes
   which FAQs. Bilingual question/answer, sort_order, is_active, soft-deletes.

TASKS:

Backend:
1. Add 'gift_cards_hero' and 'gift_cards_redeem' (or similar — match existing
   naming convention, check exact enum values in the banners table schema at
   backend/database/schema/mysql-schema.sql:797) to Banner's placement_code enum
   for the two banner slots on this page. Add a DB migration for the enum change
   (do not edit the schema dump directly — this app uses normal Laravel
   migrations, the schema dump is just a squashed snapshot for reference).
2. Add 'gift_cards' to Faq::CONTEXTS (backend/app/Models/Faq.php ~line 24).
3. Add a new customer-facing API endpoint, e.g.
   GET /api/customer/v1/page-content/gift-cards returning:
   - active banners for placement_code IN ('gift_cards_hero','gift_cards_redeem'),
     filtered by country like other banner queries, ordered/scheduled correctly
     (reuse BannerService's active-banner query logic, don't duplicate it)
   - active FAQs where context = 'gift_cards', ordered by sort_order
   Keep this endpoint read-only, cacheable (short TTL is fine — this is
   low-change marketing content), and unauthenticated (public page).
4. Register the two new gift-card banner slots in the admin banner-management
   UI (BannerController) the same way other placements are already selectable —
   confirm whether placement_code is a free select already populated from the
   enum (likely yes) or needs an explicit UI addition.
5. Confirm Faq admin CRUD (wherever Faq is currently managed in
   backend/app/Http/Controllers/Admin) already supports arbitrary context values
   via a dropdown/select — add 'gift_cards' there too if it's an explicit list.

Frontend:
6. Add an API action (follow the pattern in
   frontend/src/features/noon/gift-cards/api/gift-cards.actions.ts) to fetch
   GET /page-content/gift-cards.
7. Update frontend/src/features/noon/gift-cards/list/index.tsx to render the two
   banners from fetched data (src, alt/title, cta link) instead of hardcoded
   <Image> tags, with the hardcoded avif files as the fallback/default when no
   admin-configured banner exists for the placement (so the page never renders
   blank if content hasn't been set up yet).
8. Update faq-section.tsx to render FAQs from the fetched API list instead of
   the hardcoded i18n-key array. Preserve existing visual styling.
9. Fix or remove the dead "View all FAQs" / "Read Terms & Conditions" links
   (currently both href="/") — point them at real routes if they exist
   elsewhere in the app (search for a general FAQ page or Terms page route), or
   remove the links if no such page exists yet.
10. Confirm the page still renders correctly (no layout shift, no broken image)
    when the admin has configured zero banners/FAQs for gift_cards context —
    this must degrade gracefully, not show empty boxes or crash.

Do not build the generic page_blocks/page_sections drag-and-drop CMS system
(backend/app/Services/Shared/PageBuilderService.php) for this — that's a much
bigger system and out of scope; Banner + Faq context extension is the right-sized
solution here.

TEST: as an admin, create a gift_cards_hero banner and a gift_cards-context FAQ,
confirm they appear on the live page; deactivate them, confirm the page falls
back gracefully.
```

---

## 4. PROMPT — Resolve the dead "verify your email address" flow

```
This app sends a "Verify your email address" email on customer registration
that is completely dead: it has no clickable link (just states a raw token as
plain text) and there is no frontend page anywhere that could consume the token
even if a link existed.

CONFIRMED FACTS:
- backend/app/Jobs/SendVerificationEmailJob.php:1-38 sends the email via
  Mail::raw() (not even a proper Mailable, despite app/Mail/ existing for other
  emails) with a TODO comment "swap for a proper Mailable once email templates
  are designed". It generates a CustomerOtpToken (type: email_verification,
  Str::random(64), 24h expiry) but the email body is literally just
  "Verify your email: your token is {$token}" — no URL constructed at all.
- Dispatched from backend/app/Http/Controllers/Customer/AuthController.php:64
  (register()) and :188 (resendVerification()).
- A backend endpoint to consume the token DOES exist: POST /verify-email
  (routes/api_customer_v1.php:269-270 → AuthController::verifyEmail(),
  backend/app/Http/Requests/Customer/Auth/VerifyEmailRequest.php).
- The frontend has ZERO consumer of this — no page, no route, no API action file
  anywhere under frontend/ calls POST /verify-email. There is no
  /verify-email page under frontend/app/[locale]/.
- SEPARATELY, there is a different, actively-used, working verification flow:
  OTP-code-entry based, via backend/app/Http/Controllers/Api/Customer/
  SecurityController.php (sendEmailVerificationOtp, verifyEmailOtp), routes
  ~356-361. This is likely what customers actually see in a security/profile
  settings screen — confirm by checking whether the frontend calls these OTP
  endpoints anywhere (search frontend for sendEmailVerificationOtp/verifyEmailOtp
  or the routes' paths).

DECISION NEEDED FIRST: determine whether the link-token flow
(SendVerificationEmailJob → AuthController::verifyEmail →
VerifyEmailRequest → CustomerOtpToken type=email_verification) is:
(a) legacy/superseded by the OTP flow and should be deleted, or
(b) still intended to exist as a "click the link in your email" alternative and
    just needs to be finished.

Confirm which by checking:
- Is SecurityController's OTP flow actually wired into the live frontend
  registration/verification UI today? If yes, and it fully covers email
  verification, that's strong evidence (a) is correct — the link-flow job is
  simply unfinished dead code left over from an earlier approach.
- Check recent git history/commit messages for SendVerificationEmailJob and
  SecurityController for context on which came first / which was meant to
  replace which.

IF (a) — remove the dead path:
1. Delete backend/app/Jobs/SendVerificationEmailJob.php.
2. Remove its dispatch calls in AuthController.php (register(), 
   resendVerification() — check whether resendVerification() has any other
   purpose besides dispatching this job; if it becomes empty, remove the
   endpoint/route too, but check the frontend doesn't call it first).
3. Remove AuthController::verifyEmail(), VerifyEmailRequest, and the
   POST /verify-email route if nothing else uses them.
4. Confirm CustomerOtpToken model/table is still used by the OTP flow (type
   values other than 'email_verification') before touching the table itself —
   only remove the 'email_verification' token type usage, not the table.
5. Run a full grep across backend/frontend for any remaining reference to the
   removed symbols before finalizing, to avoid leaving dangling calls.

IF (b) — finish the flow:
1. Replace Mail::raw() in SendVerificationEmailJob with a proper Mailable class
   (follow the pattern of an existing one, e.g. GiftCardPurchasedMail in
   app/Mail/) and a real Blade email template.
2. Construct an actual verification URL:
   {FRONTEND_URL}/{locale}/verify-email?token={$token->token} — check how
   FRONTEND_URL/APP_URL is referenced elsewhere in mail templates for the
   correct config key and locale-prefixing convention used by other emails.
3. Add a frontend page at frontend/app/[locale]/(noon)/verify-email/page.tsx (or
   wherever similar auth-adjacent pages live — check where password-reset pages
   live for the sibling pattern) that reads the token query param, calls
   POST /verify-email on mount, and shows success/error/expired states.
4. Add the corresponding API action function alongside other auth actions in
   frontend/src/features/noon/.../auth (mirror existing action file structure).
5. Handle expired/already-used token states with clear user messaging and a way
   to request a new verification email (resendVerification() endpoint already
   exists on the backend — wire a "resend" button/link).

Whichever direction, do NOT leave both an unfinished link-flow AND a working
OTP-flow coexisting silently after this task — that's confusing to maintain.
Pick one, make it the only one, and document the decision in your PR
description.
```

---

## Suggested execution order

1. **Section 1** first — it's the acute bug blocking any gift-card purchase at all; small, isolated, no dependency on the others.
2. **Section 4** can run in parallel with Section 1 — fully separate code paths.
3. **Section 3** can also run in parallel — separate code paths from 1 and 2.
4. **Section 2** last, or in parallel but reviewed last — it touches the same purchase service as Section 1, so land Section 1 first to avoid merge conflicts in `GiftCardPurchaseService.php` / `CustomerGiftCardStoreController.php`.
