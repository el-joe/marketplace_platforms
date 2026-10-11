# Claude Sonnet 5.5 — Phase 3: Complete In-Page Commission Management

## Context

Continue from the current implementation on `main` at/after commit `da445ff8` and preserve all existing working changes. The unified Laravel admin page already exists at `/commissions`, with five groups of tabs, `?tab=` navigation, English/Arabic RTL support, per-tab permissions, a vendor-specific rules editor, and a new permission/migration. The current report says:

- 20 audit items are represented, but only vendor-specific rules are editable inside `/commissions`.
- 13 tabs link to existing editors elsewhere; 6 tabs have no editor anywhere.
- `npm run build` succeeded.
- Chrome QA passed 94/94 checks on a scratch database across tabs, languages, desktop/mobile widths, keyboard/back-forward/refresh, access controls, and CRUD flows.
- A fair full-suite comparison showed 576 tests / 93 failures at clean baseline and 641 tests / 61 failures on the current tree; reported regressions: none. Do not assume every failure is harmless—re-run a comparable baseline if needed.
- A fix now handles `MarketerListing.status` enum comparisons in `CartService`, and a constructor default was corrected in `MarketerConversionReversalService`. A transaction regression test was added for marketer category commission writes.
- The technical audit says some fields/settings are stored but unused, some flows are untraced, and some commission rules require a client decision.
- The client PDF is attached to the ChatGPT conversation but is not currently in the repository. Do not claim direct PDF verification unless the PDF is actually available in the VS Code workspace. The requirement summary already present in this brief is not equivalent to inspecting the original PDF.

Your goal now is to close the gap between a **unified overview with links** and a **single-page commission management experience** wherever the existing codebase safely supports it, without changing financial policy by assumption.

## Operating rules

1. Inspect the current Git diff and repository state first. Do not discard or overwrite prior work.
2. Re-read the existing commission brief, `docs/commission-refactor/client-requirements-matrix.md`, routes, controllers, FormRequests, policies, services, models, tests, and the current `/commissions` implementation.
3. Work directly in the repository; do not stop after an analysis or proposed plan.
4. Reuse existing server-side validation, authorization, domain services, and write paths. Avoid duplicating business rules in new controllers or the frontend.
5. Preserve the canonical route `/commissions`. Switching tabs or saving an in-tab form should keep the administrator on this page and restore the selected tab; `?tab=` should remain supported.
6. Use AJAX/JSON or existing project conventions where appropriate. Keep CSRF protection, server-side validation, permission checks, tenant/vendor scope, error messages, loading state, and double-submit prevention.
7. Do not change checkout calculations, payout math, ownership rules, attribution, VAT treatment, sample accounting, or commission policy without explicit requirements and regression tests.
8. Distinguish **editable and applied**, **editable but currently unused**, **system-calculated/read-only**, **analytics-only**, **report-only**, and **not implemented**. Never make an unused value look as though it affects actual money.
9. Do not create CRUD for an item whose data model or intended behavior is unknown. Add a clear explanation and mark it as blocked by a specific decision.
10. Do not run migrations against a live/shared database. Use an isolated test database for migration and browser QA. The previous report states the dev migration was already applied by another actor; verify current status read-only before making assumptions.

## Phase A — Fix the known API enum comparison bug

The report identifies the same `MarketerListing.status` enum/string comparison in `Api/Marketer/ListingController.php` around lines 45–46 (pause toggle).

- Inspect the enum definition and the code path before editing.
- Fix comparisons and assignments according to the actual enum conventions; do not guess backing values.
- Add a regression test that exercises pausing and resuming a marketer listing through the API, including persisted status and expected API response.
- Confirm this change does not make inactive listings purchasable and does not affect other listing types.
- Run the focused test and relevant marketer/cart tests.

## Phase B — Deliver real in-page editing for supported existing commission settings

### Required UX outcome

An admin should be able to manage commission settings from `/commissions` without being redirected to the old settings screen **for every setting that already has a valid, supported write flow**. Each setting remains in its appropriate dedicated tab. Existing legacy screens may remain available, but `/commissions` must provide working edit/save behavior in place.

Start with the existing configurable settings below, based on verified repository code:

1. **Marketer category rules** — use the real `marketer_commission_rules` resolver-backed storage and its supported scopes/modes. Investigate and safely resolve the legacy-table mirror problem before presenting the editor as authoritative. The existing transactional fix must stay intact.
2. **Campaign commission settings** — marketer and platform commission amounts, commission type, tier/budget fields, and the existing approval/update actions where the current role is allowed to edit them.
3. **Vendor flat-rate override and vendor commission discount** — reuse the existing validation and business methods; respect vendor-assignment restrictions.
4. **Acquisition-agent commissions** — where existing CRUD is available, expose the supported fields and actions in the same-page tab.
5. **Category commission settings** — category FBP/FBN settings, country overrides, and price tiers currently edited through existing admin screens. Reuse existing controllers/services/validation and authorization. Preserve the category ancestry and precedence behavior.
6. **Country × category marketer amounts** — expose the existing supported editor within the related tab.
7. **Flash-sale marketer bonus/invitation settings** — expose existing valid writes only; distinguish actual bonus settings from the analytics-only `commission_override_pct`.
8. **Travel campaign offer rates/package overrides** — expose supported writes only if the correct existing permissions and validation are clear. Do not claim that the travel marketer rule scope is applied until traced.
9. **Marketplace shipping rule fields** — it is valid to edit stored values only if the existing write path is valid; label `commission_type` / `commission_value` as not applied to checkout if that remains true. The report says `extra_delivery_fee` *is* applied, so preserve that distinction.
10. **Other existing editor-backed items** found during reinspection — include them if their current data source, write path, validation, and authorization are established and they fit this commission-management page.

### Implementation approach

- Prefer extracting reusable form/view components and thin actions/endpoints that call the existing domain/service logic.
- If an existing controller already handles writes correctly, reuse its application/service layer or make the minimal change needed for an in-place JSON response. Do not fork financial business logic just to support AJAX.
- Return field-level validation errors and an explicit success response. Preserve the selected `?tab=` after saving or on failure.
- Where a form handles multiple related records, use the transaction behavior already established in the project.
- Confirm editability per permission, including limited admins restricted to assigned vendors. A hidden button is not authorization; direct requests must be rejected server-side.
- Add per-tab loading, empty, error, and save states. Disable or guard repeat submissions while a request is in flight.
- Use accessible labels and keyboard-operable controls; retain Arabic translations and correct RTL layout.
- Do not silently move or delete old screens, change navigation for other roles, or make a read-only audit/reference item appear editable.

### Items that are NOT automatically safe to implement

The following items may be displayed with a clear status or linked as references, but do not invent their business rules:

- Subscription-plan discount: stored/editable but currently not applied at checkout.
- Flash-sale `commission_override_pct`: analytics-only according to the audit.
- Listing influencer/affiliate percentages: code attempts to write `influencer_commission_percentage` / `affiliate_commission_percentage`, but the audit found no columns or migration. Investigate schema, migration history, tests, and write flow. Do not add columns without establishing intended data semantics and compatibility; if necessary, report a concrete proposed schema and rationale separately.
- Global/platform-default marketer commission rules: report says no writer exists. Do not add a new precedence layer unless existing code and tests unambiguously define intended behavior.
- `travel` scope of marketer rules: no consuming flow was found. Do not label it operational without a verified consumer.
- Travel platform commission: shown in reports, but ledger/payout deduction is not confirmed. Do not alter payouts.
- Commission `tiered` rules in the `commissions` table: current UI intentionally offers only `flat` because checkout reportedly ignores `tiered`. Keep that limitation unless current source/tests prove otherwise.
- FBM-specific rate: report says there is no FBM rate and non-FBN uses FBP columns. Do not add FBM behavior without a client decision.
- `extra_delivery_fee` is applied even though shipping commission type/value are not. Do not conflate them.

## Phase C — Requirements from the client's Arabic brief

The report below is a second-hand summary, not proof of direct PDF review. Treat details as requiring source verification. If the actual PDF is added to the workspace, read it and cite page numbers in the traceability matrix. Do not claim it was read if it is unavailable.

Requirements/issues to map against the current source code:

- Admin-configured vendor commission: fixed amount such as `1.900 SAR` or a percentage, plus category-varying commission; applicable by fulfillment model.
- The meaning of `1.900 SAR` is unresolved: 1.9 SAR versus 1,900 SAR. Do not change stored values or labels based on a guess.
- Product promotion with influencers: a configurable per-influencer fee, summarized in the client document as 9 SAR per selected influencer, and a weekly deduction from the seller's statement if funds are insufficient at the time of deduction. The current code reportedly debits the influencer wallet on invitation acceptance. This conflicts with the client's summary. Do not change the payer, add weekly deductions, or add retry logic without explicit confirmation.
- Affiliate promotion is described as free.
- Influencer/affiliate commission by product category and a platform share/percentage of the marketer's earnings. The relationship between platform share and the existing marketer-profile commission discount is unresolved.
- Promotional samples: inventory deduction, one sample per selected influencer plus an admin sample, inventory minimum, financial statement entry at zero seller-facing item price with a clear promotional-sample note, and different handling for FBP/FBM vs FBN where the seller must first send product to platform warehouses.
- Influencer invitation acceptance/decline window (example 12 hours), automatic reassignment on refusal/timeout, seller/influencer notifications, WhatsApp messages, reassignment history/audit, and a suggested preference for influencers with fewer recent requests. Exact policy and ranking are not approved.
- Four influencer promotion categories with admin-defined monthly minimum obligations, progress status/alerts, and different eligible product sources.
- Do not allow one influencer to select multiple products from the same owner in a month where that rule applies.
- Product barcode/attribution must continue to credit the latest applicable influencer as described by the actual attribution design.
- VAT treatment by country for commissions/fees is unresolved. The report says VAT currently applies only to customer line totals, not commissions. Do not change tax calculations without rates, taxable components, and jurisdiction rules.

For each requirement, classify it as:
- Implemented and verified;
- Partially implemented;
- Missing;
- Contradicted by current code;
- Requires client decision;
- Out of scope for the current UI phase.

Record code paths, tests, and evidence. Preserve exact ambiguous terms such as `1.900` rather than normalizing them.

## Phase D — Commission snapshot mismatch (investigate, do not silently fix)

The report says a reconciliation test shows the charged checkout commission is 900 under the current rule “FBN uses FBN values; anything non-FBN uses FBP values,” because the listing is `fbm`. The stored order-item snapshot instead records the FBN pair (12% + 300), producing a displayed snapshot of 1260. The charged amount reportedly matches the current engine semantics, but the persisted snapshot fields disagree.

- Trace where each snapshot field is populated and overwritten.
- Add a focused regression test that expresses the intended invariant (stored/displayed breakdown must match the rule used to charge), but do not update production behavior/data until the source-of-truth mapping and historical impact are established.
- Propose the minimal safe fix in the final report. If a code change would alter persisted order-item values for new orders but not the amount charged, explain this clearly and ensure existing history is not rewritten.
- Do not change the commission rate selection or final charged amount as part of this UI task.

## Phase E — Remaining test failures

The latest report indicates 61 failing tests on the current tree versus 93 on a comparable clean baseline, with no new failing tests reported. Verify this if the current repository state allows it.

- Run focused tests for each changed surface first.
- Run the full suite with the same code, dependencies, built assets, environment variables, and isolated DB configuration for baseline and current tree if necessary.
- Categorize failures into: pre-existing confirmed, regression, environment/setup, flaky, or not yet classified.
- Do not claim a failure is stale unless verified against the equivalent baseline.
- Fix only failures that are within scope and whose intended behavior is clear. Avoid mass-refactoring unrelated tests or making broad production changes just to make the suite green.
- Report exact commands, counts, test names, and evidence for the “no new failures” claim.

## Phase F — UI and security regression QA

Repeat focused browser checks after the new in-tab editors are added. Use only an isolated/scratch database.

Verify:
- All 20 audit tabs/sections render in English and Arabic/RTL.
- Desktop and narrow viewport behavior, including 320px width.
- Every supported edit/save flow remains on `/commissions` and returns to the same tab.
- Validation errors are visible and preserve user input where appropriate.
- Permission-limited admins cannot read or mutate unauthorized data by direct requests.
- Vendor data scope is enforced server-side.
- CSRF, confirmation for destructive changes, double-submit protection, and server errors work.
- Keyboard tab navigation/focus and accessible labels remain valid.
- Direct visits to `?tab=...`, unknown tabs, refresh, back/forward, and deep-link state remain robust.
- All financial settings display whether they are actually applied, not applied, analytics-only, report-only, or informational.

Do not claim testing in another browser, a real device, or a screen reader unless actually performed.

## Definition of done

This phase is complete when:

1. The safe, existing editor-backed commission settings can be managed directly within their `/commissions` tabs wherever the current architecture supports them.
2. The page does not redirect the admin to legacy screens for a setting that this phase claims is now editable in place.
3. Every remaining read-only item carries a clear explanation and status, with a specific reason for not being editable.
4. The API marketer listing enum bug is fixed and covered by a regression test.
5. No financial policy changes were made without a confirmed requirement.
6. Focused tests, frontend build, and browser QA pass for the changed scope; full-suite failures are classified against a comparable baseline.
7. The client requirements matrix maps all requirements to code and tests, accurately identifies unimplemented features and decisions, and does not misrepresent the PDF as directly reviewed if unavailable.
8. The final diff is reviewed for unrelated changes, and no working previous changes are lost.

## Required final report

Provide:

1. **Changed files and behavior** — concise, specific summary.
2. **In-page editing matrix** — each relevant tab, now-editable controls/actions, endpoint/service reused, permission, and tests.
3. **Remaining read-only tabs** — why each cannot safely be edited in place today.
4. **Client requirement traceability** — implementation status, source path/page if PDF was actually inspected, tests, and open decisions.
5. **QA evidence** — exact test/build/browser commands and results, including comparable baseline details.
6. **Financial safety assessment** — confirm what calculation/payout paths were and were not changed.
7. **Unresolved decisions** — the precise minimal questions for the client, especially payer of the 9 SAR fee, `1.900`, platform share vs discount, tax treatment, samples/stock, acceptance/reassignment policy, FBM rates, and snapshot mismatch.
8. **Deployment steps** — migrations, asset build, cache clear, and verification steps needed; never claim deployment occurred if it did not.

Begin by inspecting the working tree, prior diff, the current `/commissions` page, and the traceability matrix. Then perform the safe implementation work rather than stopping at a plan.
