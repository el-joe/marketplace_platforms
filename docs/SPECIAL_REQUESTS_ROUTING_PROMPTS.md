# Special Requests & Smart Broker Routing — Completion Prompts (sub-agent runbook)

## Existing state (verified)
Built: `customer_special_requests` table, `marketer_profiles.broker_category_id / broker_city_id / broker_serves_all_cities`,
`App\Services\SpecialRequestRoutingService`, `Notifications\Marketer\BrokerSpecialRequestNotification`,
customer API `Api\Customer\SpecialRequestController` (index/store/show/close, routes `special-requests` in api_customer_v1.php),
marketer web `Marketer\SpecialRequestController` (index/show) + views `marketer/special-requests/*`, routes in routes/marketer.php.

## Gaps to close
1. Matching logic is duplicated in 3 places (routing service, marketer index, marketer show) and INCONSISTENT: a broker with no city and serves_all=false matches every city in the portal list but not in notifications. `index` also skips the affiliate + active checks that `show` / routing use.
2. Notifications are sent synchronously in a loop; `brokers_notified` counts marketer-admin users, not brokers. Notification text uses `title_ar` which may be null; no locale fallback. No dedupe.
3. Customer `store`: no rate limit / spam guard, `category_id` not restricted to valid (active/leaf) categories, `budget_currency` not defaulted from region, no `in_progress` transition (status enum has it but nothing sets it), close doesn't guard status.
4. Marketer side: no JSON API parity (`routes/api_marketer.php` has no special-requests), no sidebar nav link/unread badge, no way for a broker to see whether they already opened/handled a request, customer PII exposure must be limited (check what views show of `customer`).
5. Broker specialization: verify the marketer portal profile form (web + API `Api\Marketer\ProfileController`) and admin marketer page can set broker_category/city/serves_all with validation (affiliate only).
6. Frontend: NO customer page exists. Needed: `/{region-locale}/special-requests/create` (e.g. `uae-ar`), plus a list/detail page for the customer's own requests.
7. No tests anywhere.

Rules for every agent: match surrounding code style, do NOT touch files owned by another agent, do NOT commit, run relevant checks, report changed files + anything unverified.

---

## PROMPT A — Backend core: matching, routing, customer API (owns: Models/CustomerSpecialRequest, Models/MarketerProfile (scope only), Services/SpecialRequestRoutingService, Notifications/Marketer/BrokerSpecialRequestNotification, Api/Customer/SpecialRequestController, new FormRequest, migrations)
1. Single source of truth: add `CustomerSpecialRequest::scopeMatchingBroker($q, MarketerProfile $profile)` (open status; category = broker_category_id; city = null request OR broker serves all cities OR broker_city_id = request city; broker with no category matches nothing) and the reverse `MarketerProfile::scopeMatchingRequest($q, CustomerSpecialRequest $r)`. Only active `affiliate` marketers. Routing service must use these. Prompt B WILL call `CustomerSpecialRequest::matchingBroker($profile)` — keep that exact name.
2. Make the notification `ShouldQueue` (follow BaseDatabaseBroadcastNotification conventions; check queue config), afterCommit, locale fallback (`title_ar ?? title_en`, city names), and dedupe so a request notifies each marketer-admin once. `brokers_notified` = number of brokers (profiles), store separately if you also want recipients count.
3. Customer store via a FormRequest: category must exist and be active (check Category model for the right flag); city must exist and be active; budget_currency defaults to the region currency (see how other customer endpoints resolve country/region); throttle (e.g. 10/hour per customer using existing rate-limiter pattern in routes). Sanitize/strip tags on text fields.
4. Customer show/index: include status, brokers_notified, category/city names; `close` only from open/in_progress (422 otherwise). Add optional `status` filter to index.
5. Migration only if needed (e.g. index on customer_id,status). Do not rename existing columns.

## PROMPT B — Marketer portal web + API (owns: Marketer/SpecialRequestController, Api/Marketer/SpecialRequestController (new), routes/marketer.php + routes/api_marketer.php special-request routes, views marketer/special-requests/*, marketer nav)
1. Replace the inline where-clauses with `CustomerSpecialRequest::matchingBroker($profile)` (defined by Prompt A — assume it exists with that exact name). Apply the same affiliate + active guard to `index` AND `show`; non-affiliates get 403 on both.
2. Add JSON parity: `GET /api/marketer/special-requests` (paginated) and `GET /api/marketer/special-requests/{id}` in routes/api_marketer.php using the existing auth group/response style of other Api\Marketer controllers.
3. Views: show category, city, budget+currency, created date, description; show ONLY the customer's first name (or masked) — no email/phone. Empty states, pagination, AR/EN via locale. Show a hint when the broker profile has no specialization set, linking to the profile settings.
4. Add a nav entry "Special Requests" with a count of open matching requests (cache briefly) in the marketer layout/nav, visible for affiliates only.
5. Verify the broker specialization form fields (broker_category_id, broker_city_id, broker_serves_all_cities) are editable in the marketer profile (web + API) with validation (affiliate only, uuid exists); fix if missing.

## PROMPT C — Customer frontend (owns: frontend/src/features/**/special-requests/**, frontend/app/**/special-requests/**, locale en/ar keys for special requests)
1. Inspect how existing features/pages are structured (e.g. frontend/src/features/noon/checkout, app router locale/region segments like `uae-ar`) and follow it exactly.
2. Pages: `special-requests/create` (form: category select, city select with "All cities" = null, title, description, budget + currency), `special-requests` (my requests list with status badges + close action), `special-requests/[id]` (detail). Auth-required (redirect to login like other customer pages).
3. API layer: POST/GET `/special-requests`, GET `/special-requests/{id}`, PATCH `/special-requests/{id}/close` via the existing fetchInstance pattern; categories and cities from existing endpoints/hooks (find them, don't duplicate). Validation with the repo's form lib; success state shows "N brokers notified".
4. i18n: all strings in locale/en.json + locale/ar.json, RTL-safe, useLocale hook; entry link from the account menu.
5. Run `npx tsc --noEmit` and eslint on touched files.

## PROMPT D — Tests (run AFTER A and B; owns: backend/tests/Feature/SpecialRequestRoutingTest.php)
Follow existing test style. Cover: store validates and returns brokers_notified; request with city X notifies broker(city X) and broker(all cities) but not broker(city Y) nor other category; request with null city notifies every category-matching broker; non-affiliate/inactive marketer never notified; notification queued (Notification::fake / Queue::fake) once per marketer-admin; marketer index/show use identical matching (broker without city + serves_all=false sees nothing/only consistent set); non-matching request 404 on show; JSON marketer API parity; customer cannot read another customer's request; close state guard; throttle. Run new tests plus tests/Feature/Marketer and report failures honestly, including bugs in A/B (fix only clear bugs in this feature and list them).
