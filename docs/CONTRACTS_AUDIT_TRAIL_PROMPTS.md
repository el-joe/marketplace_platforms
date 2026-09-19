# Contracts & Audit Trail — Completion Prompts (sub-agent runbook)

## Existing state (verified)
Built: tables `marketer_contracts`, `marketer_contract_versions`, `marketer_contract_acceptances` (+ `orders.marketer_contract_acceptance_id`);
admin `Admin\MarketerContractController` (show/upload/acceptances/download) + blades `admin/marketers/contract*.blade.php`;
customer `Api\Customer\MarketerContractController` (show/download/accept); checkout modal in `frontend/src/features/noon/checkout/`.

## Gaps to close
1. Checkout gate (`Customer\CheckoutController` ~L564) uses only `session('marketer_attribution')`; must derive marketers from CART items (`cart_items.marketer_listing_id` -> marketer).
2. `placeOrder` does NOT enforce the gate server-side (bypassable).
3. `accept` doesn't save `marketer_id`, doesn't verify version is the ACTIVE version of that marketer's contract.
4. Customer acceptances and marketer onboarding acceptances share a table: `Marketer::hasAcceptedContract()` filters by marketer_id only -> a customer row could satisfy it. Discriminate with `customer_id` null / not null.
5. Dedup by customer+version returns an old row already linked to a previous order -> per-order audit lost.
6. Modal: no scroll-to-end gate, PDF link hits auth-only route without bearer, no AR title, gate must handle multiple marketers.
7. Admin: text_content is rendered as HTML (XSS risk), acceptances page 404s when no contract, upload not transactional, no is_required toggle (verify).
8. No tests.

Rules for every agent: match surrounding code style, do NOT touch files owned by another agent, do NOT commit, run relevant tests/lint, report changed files + anything unverified.

---

## PROMPT A — Backend customer API + checkout enforcement (owns: Api/Customer/MarketerContractController, Customer/CheckoutController, PlaceOrderRequest, Models/Marketer*, new service, migrations)
1. Create `App\Services\MarketerContractGateService` with: `requiredMarketerIds(Cart|items): array` (marketers of cart items via marketer_listing, whose contract has is_required=true and current_version>0), and `missingAcceptances(Customer, marketerIds)`.
2. Checkout prepare response: replace the session-only gate with `marketer_contract_gates: [{marketer_id, is_required, accepted}]` computed from the cart; KEEP the legacy `marketer_contract_gate` (first pending one or null) for frontend back-compat.
3. Place order: accept `contract_acceptance_ids` (array, uuid) and keep legacy `contract_acceptance_id`. Inside the transaction, verify every required marketer has an acceptance owned by this customer, for the ACTIVE version, with null order_id; otherwise 422 with a clear message. Link all to the order (`order_id`); set `orders.marketer_contract_acceptance_id` to the first.
4. Customer `accept`: validate the version belongs to the route `{marketer}` and is_active; store `marketer_id`; if an existing acceptance for customer+version has order_id NULL reuse it, otherwise create a new row (one row per order). Store ip/user_agent/accepted_at. Route param may be UUID; 404 cleanly otherwise.
5. `Marketer::hasAcceptedContract()` must only count rows with `customer_id IS NULL`. Check any writer of marketer onboarding acceptances (`Marketer/ContractController`, `Api/Marketer/ContractController`) sets customer_id null and marketer_id.
6. Add a migration only if needed (index on customer_id+marketer_contract_version_id+order_id).

## PROMPT B — Admin side (owns: Admin/MarketerContractController, admin/marketers/contract*.blade.php, admin.php contract routes, lang files)
1. Wrap upload in `DB::transaction` with `lockForUpdate` on the contract row so version_number can't race; keep old versions/files (never delete). Store PDFs under versioned unique filenames.
2. Sanitize/escape text_content wherever rendered (admin + customer). Prefer escaped `nl2br(e())` display; if HTML editing is desired, sanitize via an existing purifier in the repo (check composer.json) — do not add new dependencies without need.
3. `acceptances()` must not 404 when no contract exists: show empty state. Columns: customer, version, timestamp, IP, order number, user agent (tooltip). Add filters (version, date range) and CSV export if a pattern exists in other admin pages.
4. Add is_required toggle on the contract tab (permission marketers.manage) if missing; EN/AR titles on the form; version history table with per-version download + acceptance count.
5. Ensure the admin API paths in the spec work: POST `/admin/marketers/{id}/contract/upload`, GET `.../contract/acceptances`, GET `.../contract/versions/{v}/download`. Verify route names in `php artisan route:list --name=contract`.
6. Show the accepted contract version + timestamp on the admin order detail page (`Order::marketerContractAcceptance`) if not present.

## PROMPT C — Frontend checkout (owns: frontend/src/features/noon/checkout/**, frontend/locale/{en,ar}.json contract keys)
1. Types: add `marketer_contract_gates` to checkout type (keep legacy `marketer_contract_gate`).
2. `use-checkout.ts`: handle multiple gates sequentially (fetch contract per marketer, accept each, collect `contract_acceptance_ids`); send `contract_acceptance_ids` in place-order payload (keep the legacy single id for the first).
3. `marketer-contract-modal.tsx`: Accept button disabled until the user scrolls to the end of the content (IntersectionObserver/scroll check; PDFs: embed via blob fetch with auth header in an iframe/object, plus an explicit "I have read" fallback checkbox); show title by locale (`title_ar` when locale is ar, useLocale hook); RTL-safe; a11y (role=dialog, focus trap, Esc closes).
4. PDF: `file_url` needs the bearer token — fetch through `fetchInstance` as blob and use an object URL (revoke on unmount).
5. Add missing i18n keys in both en.json and ar.json. Run `npx tsc --noEmit` and lint for touched files.

## PROMPT D — Tests (run AFTER A and B finish; owns: backend/tests/Feature/MarketerContract*Test.php)
Pest/PHPUnit feature tests (follow existing test style in tests/Feature/Marketer/): admin upload creates v1 then v2 with v1 kept + inactive; customer show returns active only; accept stores ip/UA/marketer_id/version; accept rejects inactive/other-marketer version; placeOrder blocked (422) when required contract not accepted; placeOrder links acceptance order_id; second order creates a new acceptance row; marketer onboarding `hasAcceptedContract` unaffected by customer rows; admin acceptances page loads with no contract. Run them and the existing Marketer/Checkout suites; report failures honestly.
