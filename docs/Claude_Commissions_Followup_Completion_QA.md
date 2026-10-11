# Claude Sonnet 5.5 — Follow-up: Finish Commission Page Verification and Client Requirements Mapping

## Context

A previous implementation pass reports that a new admin `/commissions` page has been added in `backend/`, with five grouped tab areas and `?tab=` navigation. It also reports a vendor-specific commission-rule editor, a new `commission_rules.manage` permission migration, a transaction fix in `MarketerController.php`, and 58 passing tests in:

- `tests/Feature/Admin/CommissionsControllerTest.php`
- `tests/Feature/Admin/CommissionsVendorRulesTest.php`

The report also states that the migration was **not** run, browser/viewport testing was **not** performed, and the client PDF itself was not available to the previous agent. Treat all of these as claims to verify against the current working tree—not as guaranteed facts.

## Your role

Act as a senior full-stack engineer, Laravel specialist, security reviewer, and independent senior QA tester. Continue from the current working tree. Do not start over, overwrite working changes, or make broad refactors without a concrete reason. Verify what exists, finish safe verification, and report what is and is not complete.

## Hard safety rules

1. **Do not change financial calculation behavior just to make a test pass.** Do not change checkout commission formulas, precedence, payouts, marketer attribution, travel accounting, shipping money splits, or VAT behavior as part of this follow-up unless a separate, unambiguous requirement and targeted regression tests justify it.
2. **Do not run migrations against production, staging, or an unknown database.** Inspect the active environment and database target without printing passwords/secrets. If you cannot confidently prove the DB is a disposable/local development database, do not run `php artisan migrate`; report the exact blocker and give the safe command for the developer to run after verification.
3. Do not expose credentials in terminal output or the final report. Avoid printing `.env` values.
4. Do not claim browser, responsive, RTL, or manual testing unless it was actually performed.
5. Do not claim all test failures are pre-existing on the basis of incomparable test environments. A previous baseline reportedly lacked git-ignored `public/build` assets, so failure counts must be compared using equivalent code, assets, environment, database state, and configuration.
6. Preserve unrelated user changes. Inspect `git status` and the diff before editing. Never reset, clean, or discard user work.
7. Do not implement all client business features as part of this QA pass. First map them against the code and UI. Substantial new workflows must be identified as new scope or blockers, not quietly bundled into a page refactor.

## Phase 1 — Verify the current state

1. Run `git status --short`, inspect the diff and changed files, and confirm the current branch/commit. Do not overwrite the existing implementation.
2. Confirm the admin `/commissions` route exists, that it is not confused with the marketer panel's similarly named finance route, and that every `?tab=` value resolves to the intended tab.
3. Inspect controller, request validation, policy/permission checks, Blade/components, translations, migration, route registration, and tests. Verify server-side authorization for every data operation and assigned-vendor scoping.
4. Confirm the migration is idempotent/safe under the project's migration conventions, and inspect how the new permission is assigned to roles. Check whether role provisioning or a seeder also needs to know about the new permission.
5. Re-run the two targeted test files and `CommissionDiscountTest.php` if it exists. Record exact commands and results.
6. Verify the `MarketerController.php` transaction/legacy-column fix. Confirm the transaction covers the actual related writes and that tests cover failure/rollback behavior where practical. Do not expand scope unless a real bug is found.

## Phase 2 — Client requirements from the attached Arabic PDF

The previous pass did not inspect the PDF itself. Use this requirement summary as a baseline and verify it against the codebase. The original PDF may need to be copied into the repository as `docs/commission-refactor/client-requirements.pdf` for the developer/Claude workspace; do not claim you opened it unless it is actually accessible.

### A. Seller promoting a product with influencers or affiliate marketers

1. During product listing, after product details/specifications/category are provided, the seller can choose to promote the product with influencers or affiliate marketers.
2. The client describes two platform/admin commission components for the seller's product, configurable by product category and fulfillment model (the PDF mentions FBN, FBP, and FBM): a fixed admin commission written as `1.900 SAR` **or** a percentage, plus a variable category-based commission as a fixed amount or percentage.
3. Influencer promotion has an additional platform fee of `9 SAR` for each selected influencer: one = 9 SAR, two = 18 SAR, three = 27 SAR, and so on. The platform receives this revenue. The client says it should be deducted from the seller's weekly total sales statement and collected when funds become available during that week if the balance is initially insufficient.
4. Promoting with affiliate marketers is free of this per-promoter selection fee.
5. Influencer and affiliate commission rates are configurable per category. The example says perfume may pay 18%, while the platform separately keeps a profile-configured share from the influencer's or affiliate's earnings. Do not assume these semantics are identical to existing vendor commission discounts or existing marketer commission discounts; map the rules and ask for clarification where needed.

### B. Samples, inventory, and fulfillment

6. For influencer promotions, the PDF describes one product sample for each selected influencer plus one sample for the admin. For two influencers, three units are deducted from stock.
7. The PDF says the sample flow should account for the fixed and variable admin commissions as if it were a normal purchase, but the seller's financial statement should show a product price of zero and a note indicating a promotional sample. This needs accounting/inventory analysis before implementation; do not simulate a sale or adjust ledger entries without a clear, tested design.
8. Affiliate promotion is open to registered affiliate marketers and does not have the same automatic sample-per-promoter rule. The admin decides the number of samples, if any, when the seller requests affiliate promotion.
9. The admin configures a minimum inventory threshold for promotion.
10. The PDF explicitly says FBP and FBM sellers must send products to the admin warehouse before the promotion can proceed. The technical audit separately reports no distinct FBM rate and says FBM/cross-dock use FBP columns. Preserve both statements as separate findings until confirmed; do not silently reconcile them.

### C. Influencer selection, acceptance, replacement, and notifications

11. Each selected influencer has a configurable acceptance window; the example is 12 hours. If the influencer rejects or the window expires, the system automatically selects a replacement.
12. The PDF suggests prioritizing the influencer who received the fewest seller promotion requests during the previous month. Treat this as a proposed selection algorithm, not confirmed existing behavior.
13. Notify the seller, the original influencer, the replacement influencer, and admin/logs about the reassignment.
14. Send the influencer a WhatsApp message to the registered number with a link to accept or reject. Inform the seller of the influencer's decision; after rejection, tell the seller a replacement is being sought.

### D. Four influencer product/promotion categories

15. **Category 1:** influencers must complete an admin-defined minimum number of ads each month from seller promotion requests. Show progress (example `2/3`); warn in red and notify if they do not meet the monthly minimum. Completion may exceed the minimum.
16. **Category 2:** influencers choose at least an admin-defined number of products from other influencers' own stores/open marketplace, not seller-owned products. Set/display commissions by category and show the platform's deduction. The same promoting influencer must not select more than one product from the same product-owner influencer in a month.
17. **Category 3 (intermediaries):** admin assigns products for influencers to choose from. These may be existing platform products or newly added products. Influencers must promote the admin-defined minimum and earn commission per sale.
18. **Category 4 (Nawy's own products):** same described mechanism as category 3.
19. Notify influencers through WhatsApp during the last week of the month if they are short of the minimum for any category. The client mentions possible contractual penalties; do not implement fines without explicit business rules and approval.
20. When listing a Nawy Now product, provide an option to include it in category 3 or 4, while retaining separate admin screens for assigning products to each category.
21. When a promotion moves from one influencer to another, the barcode/attribution must direct attribution to the latest influencer whose social promotion the customer saw. Avoid double attribution, stale links, and incorrect commission assignment.

### E. Platform share and VAT

22. The client says the platform receives a profile-configured percentage of the **net commissions** earned by influencers, affiliate marketers, and intermediaries across all sections. Example: from 100 SAR net commission, the platform keeps 30 SAR and the marketer receives 70 SAR. Verify what “net commission” means and how this differs from existing marketer commission discount columns/services before wiring anything.
23. The client states that all commissions in every section have VAT according to the applicable country. This is a potentially material accounting/tax requirement. Do not invent tax rates or jurisdiction rules; identify the existing tax service, country configuration, and invoice/ledger path, or mark as blocked pending decisions.
24. **Ambiguous amount:** the PDF writes `1.900 SAR`. Do not decide whether this means 1.9 SAR or 1,900 SAR without client confirmation.

## Phase 3 — Build a real traceability matrix

Create or update `docs/commission-refactor/client-requirements-matrix.md` (or report the same table in the final response). Include every PDF requirement numbered 1–24 above and each relevant one of the technical audit's 20 commission items.

For each row, record:

- Requirement ID and concise source-derived wording.
- Current implementation found (file/class/route/table when verified).
- Status: `Implemented`, `Partially implemented`, `Missing`, `Not applicable`, or `Needs client decision`.
- Whether it is part of the current `/commissions` UI refactor or a separate feature.
- Test evidence or missing test.
- Dependency/blocker/decision needed.

Do not infer implementation from a database field or input alone; trace whether the value is actually read by the calculation/workflow. Call out any mismatch between the client PDF and technical audit. In particular, distinguish UI configuration from effects on checkout, the ledger, the seller statement, inventory, payout, attribution, and tax.

## Phase 4 — Asset build and safe migration verification

1. Inspect `package.json` scripts and the Tailwind/build setup. Run the repository's correct frontend asset build command (for example `npm run build` only if that is the actual script) if dependencies/environment permit. Report the exact command and outcome. Do not change dependency versions without need.
2. Inspect the current database target safely. If and only if it is confirmed to be local/disposable development data, apply the migration, then verify the permission exists and the role assignment works. If this cannot be confirmed, do not migrate; provide safe manual next steps instead.
3. Check that the generated/ignored `public/build` assets exist and correspond to the current source before comparing the full test suite against a clean baseline.
4. Avoid exposing `.env` contents or DB credentials in logs/report.

## Phase 5 — QA and regression testing

### Automated

- Run focused new admin commission tests and existing commission-related tests.
- Run static analysis/formatting/lint/type-check/build tools configured in the project.
- Run the broader relevant suite if resources permit.
- Compare test failures to a baseline with equivalent built assets, configuration, database migrations, and runtime dependencies. If equivalence cannot be established, say the regression comparison is inconclusive instead of asserting no new failures.
- Test the new permission for authorized and unauthorized admin users, including assigned-vendor restrictions.
- Test all tab values, invalid/unknown tabs, empty states, and refresh/deep-link behavior.
- Test edit validation and audit logging for vendor-specific commission rules, including global rules without vendor/category, duplicate submissions, transaction failures, and effective date/range validation.
- Confirm no calculation regressions are introduced for checkout, payouts, marketer conversion ownership/discounts/bonuses, travel reports, and shipping.
- Investigate the two reported `CommissionReconciliationTest` failures without immediately changing financial code: (a) snapshot has 12% + 300-per-unit while final engine amount appears to include only 300-per-unit, and (b) place-order returns 422. Determine the exact cause and classify as pre-existing, environment-related, or newly introduced using comparable setup. Do not fix financial behavior without a clear expected result from tests/business rules.

### Browser/manual

If browser automation or a local browser is available, run the app in a safe local/dev environment and inspect `/commissions` at desktop and narrow/mobile widths, in English and Arabic/RTL. Verify every tab, badges, links, forms, table overflow, focus/keyboard navigation, error/success messages, permission-denied UI, and browser refresh/back-forward behavior. Capture useful evidence/screenshots if tooling supports it.

If migration or build prerequisites block manual QA, finish all possible read-only/static checks and report the exact blocker and the commands the developer must run. Do not state the UI is visually verified when it is not.

## Phase 6 — UX implementation-scope check

The current report says only technical audit item 2 (vendor-specific commission rules) is editable directly inside `/commissions`; other tabs show live data and links to the pre-existing editors. Verify this matches the agreed task. The original goal was to consolidate commission management into a one-page tabbed experience, so report clearly whether each tab is:

- An editor embedded directly in `/commissions`;
- A read-only summary/details view; or
- A link to an existing editor outside the page.

Do not silently claim that the one-page consolidation is fully complete if the user has to leave `/commissions` to edit most commission types. If the current scope means “one unified directory/overview, with existing editors retained,” state that clearly. If “all editing must happen in this page” remains a requirement, provide a small, staged plan for bringing supported editors into tabs without duplicating business logic.

## Deliverables and final response

1. Summary of verified changes; list important files and exact diff scope.
2. Completed requirements traceability matrix (technical audit items and client PDF requirements).
3. Exact commands and pass/fail outcomes for tests, migration, build, lint/static analysis, and browser checks.
4. Honest test-baseline comparison; clearly separate pre-existing failures, environment failures, inconclusive comparisons, and any failures introduced by the change.
5. Confirmation of what each `/commissions` tab actually does: embedded editor, read-only view, or external link.
6. Financial regression assessment; explicitly discuss the two reported reconciliation failures and whether they were resolved or left as a documented separate issue.
7. Client decisions still needed, especially `1.900 SAR`, weekly 9 SAR influencer fees/collection behavior, definition of “net commissions” and profile share, VAT jurisdiction/calculation, sample accounting, acceptance/replacement policy, and the four-category obligations.
8. Concrete next steps only. Do not claim “done” if browser checks, important tabs, migration, or requirements mapping remain incomplete.

Start by inspecting the existing working tree and then continue safely from the current implementation.
