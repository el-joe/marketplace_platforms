# Claude Sonnet 5.5 — Commission Management Refactor, Client Requirements & Senior QA Brief

## Role and quality bar

Act as a **senior full-stack engineer, Laravel architect, frontend engineer, and independent senior QA tester**. Work directly in the existing repository and follow its actual conventions. Do not merely provide advice or a proposed plan: inspect the codebase, implement the refactor, run appropriate tests, fix failures caused by your changes, and report verified results.

Use BOTH source documents: (1) **Marketplace Commission Report** Markdown as the technical audit of current code paths and known gaps; and (2) the client PDF **نظام تسويق البائع مع المشاهير والمسوقين بالعمولة** as the business-requirements source. The technical audit describes what was found in code; the PDF describes what the client expects. Neither source proves the feature is currently implemented. If the source files are available in the workspace, read them in full before coding. Suggested workspace paths are `docs/commission-refactor/Marketplace-Commission-Report.md` and `docs/commission-refactor/Client-Commission-Requirements.pdf`. If a file is missing, use the summarized client requirements in this brief and explicitly record that you could not inspect the original file.

## How to use this brief in VS Code

Save this file in the repository (for example, `docs/commission-refactor/Claude-Commission-Refactor-Brief.md`). Put the original technical audit Markdown and client requirements PDF alongside it if they are not already in the workspace. Then paste the short starter prompt below into Claude Sonnet 5.5 in VS Code:

> Read `docs/commission-refactor/Claude-Commission-Refactor-Brief.md` completely, then inspect both source documents in the same folder. Execute this task end-to-end in the current repository. Do not stop at a plan or empty tab scaffolding. First inspect the current `/commissions` implementation and write the source-backed requirement mapping; then implement the unified one-page tabbed experience, run senior-level QA and financial regression tests, fix failures caused by your changes, and report exact evidence. Keep the UI refactor separate from new financial/accounting/workflow behavior that needs client clarification. Do not claim tests or browser checks you did not run.

## Main objective

Refactor commission management into **one unified `/commissions` page**, with each commission type managed or explained in its own dedicated tab. Keep the user on `/commissions` while switching tabs; do not create a separate page/route for every commission type.

Use a professional, production-ready admin UX: consistent layout, clear labels, sensible grouping, responsive behavior, accessible controls, useful loading/empty/error states, and confirmation for destructive actions. Reuse the project's existing design system, components, authorization rules, localization patterns, and coding conventions.

### Important interpretation of the tabs

There are many commission types and related settings. Do not create an unmanageable wall of equally prominent top-level tabs if the existing UI framework has a better pattern. Use **grouped primary tabs with dedicated secondary tabs or a well-designed tab navigation** so that every commission type in the technical audit and every in-scope client commission setting has its own identifiable section while all sections remain inside `/commissions`. Preserve a clear distinction between a configurable commission rule, an actual transaction charge/deduction, an influencer/affiliate earning, the platform's share of that earning, and a purely analytical value.

Example grouping:
- **Platform & Vendor**
- **Marketer**
- **Acquisition Agents**
- **Travel**
- **Shipping Rules & Related Settings**

Within each group, provide a separate tab/section for every applicable type below. Choose the implementation that best fits the existing admin UI, but preserve the one-page requirement.

## Phase 1 — Inspect before changing code

1. Inspect the repository structure and identify the actual admin application and stack. The audit mentions Laravel Blade admin/partner/vendor/marketer/travel-agency/carrier/delivery panels, APIs, Next.js, and Flutter apps. Do not assume every surface is part of this admin-page task.
2. Find the existing `/commissions` route, controller, view/component, navigation item, permissions, translations, tests, and related APIs. Establish which application owns the route and preserve its current behavior.
3. Read the complete Marketplace Commission Report and trace each proposed tab to the real model, service, controller, request validation, route, permission, and existing UI.
4. Search for existing commission components and CRUD flows before creating new ones. Reuse them where practical; avoid duplicate business logic.
5. Establish a baseline: run relevant existing tests and identify pre-existing failures. Record commands and results.
6. Create a concise implementation checklist before coding, then proceed with implementation without waiting for another prompt unless a genuinely blocking ambiguity or destructive decision arises.

## Phase 2 — Commission types that must be represented

Every item below must be accounted for in the unified interface. Give each a dedicated tab/section. The UI must accurately distinguish **editable**, **read-only/system-calculated**, **not currently wired**, and **stored but not consumed** functionality.

### A. Platform & Vendor

1. **Platform commission rules** — category-based FBP/FBN rates and fixed amounts, category inheritance, country overrides, and price tiers.
2. **Vendor-specific commission rules** — rules in the `commissions` table, including vendor/category matching, rate type, minimum/maximum, effective dates, and priority. The audit reports no existing UI writer. Verify the code first; if no supported CRUD flow exists, implement it safely only if the current schema and business rules provide enough information. Otherwise provide an honest status and a clearly documented blocker—never invent schema or precedence.
3. **Vendor flat-rate override** — `vendors.commission_rate`, including the existing greater-than-zero semantics.
4. **Vendor commission discount** — `commission_discount_type`, flat/percentage amounts, and notes.
5. **Subscription-plan commission discount** — `subscription_plans.commission_discount_pct`. The audit says it is currently stored/editable but not applied at checkout. Do not claim that the discount works. Preserve existing behavior and mark the application gap clearly; do not silently alter checkout math as part of a UI-only refactor.
6. **Platform-as-seller/admin listing commission** — explain/display the system-calculated 100% platform share where relevant; do not fabricate vendor payout settings.
7. **Flash-sale commission override percentage** — `flash_sales.commission_override_pct`. The audit says it is analytics-only, not an actual checkout override. Label it accurately.

### B. Marketer

8. **Campaign commission types** — fixed, percentage, last-click, and tiered, including marketer and platform commission amounts, budgets, tier rules, and relevant approval workflow. Respect existing controller/service validation.
9. **Marketer category commission rules** — `marketer_commission_rules` and scopes (`products`, `open_market`, `travel`), including marketer-specific/platform-default rules, category inheritance, exclusions, fixed/percentage/both modes, and minimum amount.
10. **Legacy marketer category commission stores** — `marketer_category_commissions` and `open_market_category_commissions`. Verify whether existing admin forms write to a different table than the resolver reads. Do not create a misleading editor that writes only to a table the live resolver ignores. Surface the discrepancy and either correct it safely with tests or report it as a separate verified blocker.
11. **Country × category marketer amounts** — influencer and affiliate amounts in `marketer_commission_country_settings`.
12. **Vendor-listing influencer/affiliate commission percentages** — `influencer_commission_percentage` and `affiliate_commission_percentage`, with current configured minimums. The audit says storage/validation exists but did not find a consumer in conversion calculation; show the distinction accurately.
13. **Marketer commission discount** — flat/percentage discount on marketer earnings via `MarketerProfile::applyCommissionDiscount()`.
14. **Flash-sale marketer bonus** — invitation bonus settings (`extra_commission_rate`, mode, and flat amount) and the existing calculation/status rules.
15. **Platform share of a marketer campaign** — `platform_commission_amount`, including who owns/pays the campaign and existing approval workflow.

### C. Acquisition agents

16. **Vendor acquisition-agent commission** — rate in basis points, monthly minimum/maximum completed sales, validity dates, status, earned total, and existing admin assignment/management flow. Preserve the distinction that this is an internal staff earning and is not deducted from vendor payout.

### D. Travel

17. **Travel platform commission** — environment/config-driven rate (default 10% according to the audit). It currently appears to be a report calculation, with no ledger/payout deduction found. Do not present it as a confirmed collected fee without tracing and verifying the actual flow.
18. **Travel-agency campaign offer commission** — offer rate/type and per-package override, including existing admin approval and travel-agency workflow. Verify whether the travel marketer rules scope is consumed before claiming end-to-end functionality.

### E. Shipping rules & related money-split settings

19. **Marketplace shipping-rule commission** — fixed/percentage/mixed type, value, and extra delivery fee. The audit reports these values are stored/displayed but not used in checkout calculation. Label the current behavior accurately; do not imply it changes checkout totals.
20. **Related fees and earnings reference section** — gateway fee, COD fee, warranty revenue, shipping revenue/subsidy, delivery-agent fees, carrier rates/shipping gap, influencer fee, FBN fees, and paid-ad attribution. These are not all commissions. Include them as a clearly separated reference/status section only if appropriate to the existing `/commissions` purpose; do not misclassify them as commission CRUD features.

**Coverage requirement:** Produce a mapping table in your final report with every numbered item above, its tab/section, whether it is editable or read-only, the underlying source(s), and its test coverage/status. If an item is not implemented, explain exactly why.

## Phase 2B — Client PDF business requirements (read carefully)

The Arabic PDF is the client's business requirements, not a code audit. Use it to understand the intended business model, identify which existing commission settings belong in `/commissions`, and verify whether the backend implements the intended behavior. Do not assume that a requirement is already supported just because a similar database column exists.

### 2B.1 Seller product promotion costs

The client describes the following model when a seller asks to promote a product:

1. **Fixed admin commission**: the PDF says `1.900 Riyal` or a percentage. This value is ambiguous in numeric formatting. Do not hard-code it, reinterpret it, migrate data, or change financial behavior until you establish the existing product's currency conventions and whether `1.900` means 1.9 SAR or 1,900 SAR. If the repository does not disambiguate it, record it as a client clarification required.
2. **Variable admin commission by category**: admin defines a fixed amount or percentage per category and fulfillment model. The PDF explicitly mentions FBN, FBP, and FBM. The technical audit found FBP/FBN resolution paths but does not establish equivalent FBM commission support. Verify real code/schema behavior before exposing an FBM option; never fake a working mode.
3. **Paid influencer promotion fee**: the PDF specifies 9 SAR per selected influencer: 1 influencer = 9 SAR, 2 = 18 SAR, 3 = 27 SAR, and so on. The fee belongs to the platform and is deducted from the seller's financial statement against weekly sales, with deductions attempted when funds become available if the balance is insufficient. Verify whether this fee, ledger/accounting entries, weekly deduction logic, retries, and audit trail exist. Do not implement a financial deduction as a mere UI calculation or silently add a new deduction flow as part of a presentation-only refactor.
4. **Affiliate promotion fee**: the PDF says promotion through the general affiliate-marketer system is free. Do not apply the per-influencer fee to affiliate promotion unless code or an explicit updated requirement says otherwise.
5. **Different commission rules for influencer vs affiliate**: admin sets product-category commission levels. The PDF gives perfume at 18% as an example and says the platform takes a separately configured share from the influencer's or affiliate's earnings, configured in each profile. Distinguish the original gross commission, the platform's share/cut, and the person's final net payout. Do not equate this platform share automatically with the existing `MarketerProfile::applyCommissionDiscount()` feature; trace whether they represent the same business concept or different ones.

### 2B.2 Product samples, stock and fulfillment (adjacent workflow)

The PDF describes product samples for influencer promotion:

- Each selected influencer receives one sample and the admin/warehouse receives an additional sample; selecting two influencers therefore results in three samples deducted from inventory.
- Sample stock is deducted. The PDF says the fixed and variable admin commissions apply as in a normal purchase, but the seller's financial statement must show the product price as zero and describe the line as a promotion sample, not show the seller as owed the product sale price.
- The general affiliate program does not have a standard public offer sample in the same way; the admin determines sample quantity when requesting affiliate promotion.
- Admin configures the minimum stock threshold required for promotion.
- For FBP/FBM (as written in the PDF), sellers must be warned that the products must be sent to the admin warehouse before promotion can proceed.

These are financial/inventory dependencies, not simply tab styling. Trace whether existing code supports them. Include a reference/status section in `/commissions` only when it is useful and authorized. Do not rebuild inventory, sample fulfillment, shipping, or warehouse workflows as part of this UI refactor unless they are already within the current commission management scope. Surface missing functionality and propose a separate task when substantial workflow development is necessary.

### 2B.3 Influencer promotion request lifecycle

The PDF describes a configurable acceptance window (example: 12 hours):

- An influencer may accept or reject a seller's promotion request within the admin-configured window.
- Rejection or timeout causes an automatic search for a replacement influencer. The client suggests prioritizing influencers with fewer seller promotion requests in the previous month.
- Seller, previous influencer, new influencer, and admin should be notified of the reassignment; admin needs a report/audit trail of the transfer.
- The request should be sent to the influencer via WhatsApp using the configured phone number and a link to accept/reject. The seller receives the influencer's decision. On rejection, the seller should be told that a replacement is being searched for.

Treat these as adjacent campaign workflow requirements. Do not fake WhatsApp delivery or automated reassignment in the UI. Verify actual jobs, scheduled tasks, notification providers, time-zone/deadline handling, idempotency, and audit data if this flow is already integrated.

### 2B.4 Four influencer / marketing product categories

The PDF describes four operating categories. The exact names and enum/database mapping may differ in the current code; trace rather than assume:

1. **Seller-requested influencer promotion**: admin sets the minimum monthly number of seller-requested promotion advertisements for each influencer. A dashboard shows progress such as `2/3`; when below the monthly requirement, show a warning/failed status (the PDF suggests red) and notify in the final week of the month. Exceeding the minimum is allowed.
2. **Open marketplace products owned by other influencers**: an influencer chooses at least the admin-configured number of products from other influencers' own store/open-market listings, not seller products, and earns the configured category commission. The PDF gives an example of at least four product promotions. The system should not let one influencer promote more than one product belonging to the same other influencer in the same month. The marketer must be able to identify whose product they are promoting.
3. **Intermediary / broker category**: admin selects or reassigns existing products to this category or adds new products. Influencers choose from this category, fulfil the configured minimum, and earn commission per attributable sale.
4. **Platform-owned Nawy products**: the PDF says this follows the same mechanism as category 3. For applicable product creation (including Nawy Now), the UI may offer an option to expose a product in category 3 or 4, while retaining a separate dedicated screen for managing products in each category.

Admin configures monthly minimum promotion counts separately per category. The PDF also asks for WhatsApp alerts during the final week when an influencer is below a category's required count. For Nawy Now product creation, an optional category 3/4 placement should not replace the dedicated category product management interface.

Treat these as business-domain context for categorizing marketer commission rules and campaign tabs. This task is not authorization to build all four product-management experiences from scratch. Map existing types/scopes/models/controllers to the PDF categories and document any mapping that cannot be established.

### 2B.5 Attribution, platform share and tax

- **Last influencer attribution / barcode**: when promotion moves from one influencer to another, the product barcode/attribution data may need to reflect the last influencer whose social media advertisement the customer saw. The customer scans the product barcode and is attributed to the latest applicable influencer. Trace this against the existing last-click attribution flow. Do not change barcode generation, attribution priority, or commission ownership without regression tests and unambiguous existing business rules.
- **Platform share of net marketer commission**: the PDF says the platform takes a configured percentage from the *total/net commission earned* by each influencer, affiliate marketer, or intermediary, configured per person's profile. Example in the PDF: if the gross/net-before-platform-cut commission is 100 SAR and the platform cut is 30%, the platform keeps 30 SAR and the person receives 70 SAR. Preserve the PDF's stated concept, but inspect exact terminology in code because the PDF's use of “net commission” may not map one-to-one to current field names.
- Keep separate financial concepts separate: (a) product/platform commission paid by the seller, (b) influencer/affiliate commission paid by the campaign owner, (c) the platform's share of a marketer's earnings, (d) a discount reducing a commission, and (e) influencer promotion service fees charged per selected influencer.
- **VAT**: the PDF states that commissions are subject to VAT based on the country. Check whether existing country tax/VAT configuration and invoicing rules apply to each commission/fee. Do not invent VAT rates, silently add tax to amounts, or assume every commission field is taxable in the same way. Report any unresolved tax treatment as a business/accounting clarification.

### 2B.6 Requirement reconciliation rules

Before implementation, create a **Client Requirement Mapping** table with columns:

- PDF requirement (brief label)
- Existing model/table/field/service/route/job (actual repository evidence)
- Current behavior (verified, not inferred)
- Relevant `/commissions` tab/section
- Status: implemented / partially implemented / missing / uncertain / out of UI-refactor scope
- Required action: UI exposure / safe bug fix / separate backend project / client clarification
- Tests needed

At minimum include: fixed admin commission and the `1.900 SAR` ambiguity; category/fulfillment-specific variable commission; FBN/FBP/FBM differences; 9-SAR-per-influencer promotion fee and weekly seller-statement deduction; free affiliate promotion; influencer-versus-affiliate category commission; per-profile platform cut; product samples and seller statement treatment; minimum-stock and warehouse prerequisite; acceptance deadline/reassignment/notifications; four influencer categories and monthly minimums; per-category marketer commissions; same-owner monthly selection restriction; last-influencer barcode/attribution; and country-specific VAT.

If client PDF expectations conflict with current code or the technical audit, do not silently choose a behavior. Preserve production financial behavior during the UI refactor, surface the conflict in the UI only if a truthful status can be shown, and list required decisions in the final report. Separate the **one-page UI refactor** from **new financial/accounting/workflow behavior** that requires a distinct implementation decision.

## Phase 3 — UX and interaction requirements

- Keep one canonical route: `/commissions`. Tab selection must not navigate to separate commission pages. If useful, preserve tab selection with a query parameter/hash, while retaining `/commissions` as the canonical page.
- Make the navigation usable on desktop, tablet, and mobile. Avoid clipped tabs; use grouped/scrollable tab navigation or another accessible pattern.
- Each tab must have a clear title, short explanation of what the commission means, who pays, who receives, and whether the value is actually applied to transactions or is informational only.
- Use consistent table/form patterns, formatting, filters, pagination, search, sorting, and action menus where appropriate to the existing project.
- Show money with the app's existing currency/base-unit conventions. Do not introduce floating-point monetary calculations or change the backend's integer money storage. Clearly distinguish percentages from basis points.
- Provide validation feedback, saving/loading indicators, success/error messages, empty states, and confirmation before destructive changes.
- Respect existing roles, policies, guards, permissions, and tenant/vendor scoping. Never expose one vendor's data to another.
- Use the project's localization system. If Arabic/English or RTL is already supported, ensure the new page works in both LTR and RTL layouts. Do not hardcode UI strings when the project has translation files.
- Follow accessibility basics: keyboard-operable tabs, visible focus, proper labels, meaningful headings, and ARIA semantics consistent with the framework.
- Avoid one giant component/controller. Split the UI into maintainable components and reuse existing service/domain logic.
- Do not add unnecessary packages or redesign unrelated pages.

## Phase 4 — Business logic and safety constraints

This task is primarily a commission-management UI refactor. **Do not change commission calculation behavior merely to make the new UI appear complete.**

- Preserve existing precedence and formulas, including vendor/category rule resolution, category parent traversal, FBP/FBN and country overrides, price tiers, vendor discounts, marketer discounts, flash-sale bonuses, attribution, and payout ownership.
- Do not duplicate commission formulas in the frontend.
- Do not assume that a setting is active just because it exists in a database column or form.
- For every setting reported as unused or uncertain by the audit, trace the actual call path and test the conclusion. Separate verified facts from assumptions.
- If a true functionality bug is found (for example, an editor writes to a table the resolver does not read), do not silently bundle a risky financial behavior change into the UI refactor. Make a minimal, tested fix only if the intended behavior is unambiguous from existing code/tests; otherwise document the issue and propose a separate scoped fix.
- Keep changes backward-compatible. Avoid destructive migrations and schema changes unless strictly necessary and justified.
- Use database transactions where multiple related records must change atomically.
- Enforce validation and authorization server-side, not only in the UI.
- Add audit logging for financially significant edits if the project already has an established audit mechanism.
- Do not expose secrets, environment values, or sensitive personal data in the interface or logs.

## Phase 5 — Senior QA test plan

Act as an independent senior QA tester after implementation. Do not treat “the page renders” as sufficient.

### A. Automated checks

Run all relevant checks available in the repository, such as:
- Focused Laravel feature/unit tests for routes, authorization, validation, CRUD behavior, and commission rules.
- Existing commission-related tests, including `tests/Unit/CommissionDiscountTest.php` if present.
- Frontend lint, type-check, and build commands if a frontend component is involved.
- Formatting/static analysis tools already configured by the repository.
- Relevant API tests and regression tests for the commission calculation/payout paths.

Add tests for:
- The unified `/commissions` route renders for authorized users.
- Unauthenticated/unauthorized users cannot access or mutate commission settings.
- Each tab/section is present and its data is scoped correctly.
- Create/update/delete flows work where CRUD is supported.
- Invalid percentages, negative amounts, invalid dates/ranges, invalid enum values, and malformed tier rules are rejected server-side.
- Country/category/vendor/marketer scoping and authorization are enforced.
- Category inheritance, country overrides, price tiers, min/max clamps, vendor discounts, marketer discounts, campaign tier selection, and flash-sale bonuses remain unchanged where covered by existing business logic.
- Settings identified as informational/unused do not falsely alter calculated commissions.
- Tab switching, deep links/query state (if implemented), browser refresh, and back/forward behavior work correctly.
- Empty, loading, validation-error, server-error, and success states are usable.
- LTR/RTL and responsive layouts do not hide controls or make navigation unusable.

### B. Manual/exploratory QA

Use a browser or the project's available UI test tooling if available. Inspect:
- All tabs and their actions, not only the first screen.
- Desktop and narrow/mobile viewport behavior.
- Arabic/RTL if supported.
- Long labels, wide tables, large datasets, empty datasets, and validation errors.
- Permission-denied behavior and destructive-action confirmations.
- Network/server failures and duplicate submission protection.
- Any financial summary/amount display for rounding, percentage, and basis-point mistakes.

Do not claim browser/manual testing if no browser or UI test tool was actually available. State exactly what was and was not verified.

### C. Financial regression guardrails

Use existing test factories/fixtures and add targeted regression tests where practical. Confirm that the refactor does not change:
- Checkout commission amounts or resolution order.
- Vendor payout totals and platform net calculations.
- Marketer conversion commissions, ownership, discounts, bonuses, pending/released balances, and reversals.
- Acquisition-agent monthly earning calculations.
- Travel reporting calculations.
- Shipping fee/subsidy calculations.

Never use production financial records for test mutations. Use isolated test data/database.

## Phase 6 — Implementation workflow

1. Inspect and summarize the existing architecture and the current `/commissions` implementation.
2. Map each audit item to its existing implementation and proposed UI section.
3. Implement the unified page and all supported tab content using existing patterns.
4. Add/update route, controller/service, authorization, validation, localization, and UI tests as needed.
5. Run focused tests first, then broader relevant checks.
6. Fix regressions introduced by the changes; rerun the failed checks.
7. Review the final diff for accidental changes to checkout, payout, attribution, or unrelated modules.
8. Do not stop after producing a plan or after scaffolding empty tabs. Implement every section that can be safely implemented from the existing codebase. Clearly identify genuine blockers.

## Definition of done

The task is complete only when:
- `/commissions` is the single unified entry point.
- Every commission type in the audit is represented by its own identifiable tab/section.
- Existing supported workflows work, with authorization and validation enforced.
- Read-only, unused, analytics-only, and uncertain settings are clearly labeled rather than falsely represented as active.
- The design is responsive, accessible, consistent with the application, and localized according to existing conventions.
- Relevant tests and checks have been run, failures caused by the changes have been fixed, and results are reported honestly.
- No unintentional financial calculation or payout behavior changes have been introduced.
- The final diff is reviewed and there are no unrelated changes.

## Required final response from Claude

When finished, provide:

1. **Implementation summary** — what changed and which files/components/routes were affected.
2. **Tab map** — a table mapping every numbered technical-audit item above to its tab, editable/read-only status, data source, and test coverage.
3. **Client Requirement Mapping** — the table required in Phase 2B.6, showing which PDF requirements are implemented, partial, missing, uncertain, or out of UI-refactor scope, with repository evidence and next action.
4. **QA evidence** — exact commands run and pass/fail results. Separate pre-existing failures from failures caused by the changes.
5. **Manual test results** — what was actually tested, browser/device/viewport if applicable, and anything not tested.
6. **Financial regression assessment** — evidence that checkout, payouts, marketer earnings, travel, and shipping calculations were not unintentionally changed.
7. **Known gaps/blockers** — especially the audit's reported unused settings, missing writers, parallel marketer rule stores, untraced flows, and client-PDF requirements that are missing or ambiguous.
8. **Remaining work** — specific actionable items only; do not say “all done” if a section or verification is incomplete.

Start by inspecting the repository and the existing `/commissions` implementation. Then execute the task end-to-end.
