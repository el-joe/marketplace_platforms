# Senior Full-Stack Engineer – Client Requirements Implementation

You are acting as a **Senior Full-Stack Software Architect and Engineer** responsible for analyzing, designing, and implementing a set of new business requirements in an existing production-level application.

Your task is to inspect the existing codebase, understand its architecture and current business logic, and implement all requirements below professionally.

**IMPORTANT: This is an implementation task, not a code review or a proposal. You are expected to execute the changes directly in the existing project.**

---

## 1. First: Understand the Existing System

Before writing or modifying any code:

1. Inspect the entire project structure.
2. Identify the backend framework, frontend framework, database structure, authentication system, authorization/permissions, and existing architectural patterns.
3. Understand the existing portal, employee management, user roles, contracts, classified advertisements, influencers/celebrities, brokers/intermediaries, categories, and product modules.
4. Trace existing workflows and identify all related controllers, models, services, repositories, components, APIs, migrations, routes, and frontend pages.
5. Review existing database relationships and determine how the requested features should integrate with the current architecture.
6. Identify existing functionality that can be reused instead of creating duplicate implementations.

Do not assume that the current database structure or implementation is correct. Verify it.

**Critical rules:**
- Preserve existing functionality and backward compatibility.
- Do not create duplicate modules or unnecessary database structures.
- Follow the project's existing coding standards and architectural conventions.
- Avoid hardcoded business rules.
- Use database-driven configurations wherever appropriate.
- All changes must be production-ready, maintainable, secure, and scalable.

---

# 2. Requirements and Implementation Details

## Requirement 1: Portal Content Management Permissions

### Business problem
The client cannot properly grant employees permissions to modify portal content.

### Required implementation
Review the current roles and permissions system and implement a clear permission management mechanism that allows administrators to control which employees can manage portal content.

The system should support granular permissions such as:

- View portal content.
- Create portal content.
- Edit portal content.
- Delete portal content.
- Publish/unpublish content.
- Manage portal sections and configurations, where applicable.

Requirements:

1. Administrators must be able to assign these permissions to employee roles or individual employees, according to the existing authorization architecture.
2. The employee management interface must clearly display the available portal permissions.
3. Employees must only see and perform actions authorized for them.
4. Permissions must be enforced on the backend, not only hidden in the frontend.
5. Unauthorized API requests must return the appropriate authorization response.
6. Ensure existing roles and permissions continue working correctly.

Do not introduce a second authorization system if one already exists.

---

## Requirement 2: Portal Analytics and Registration Funnel

### Business problem
The client needs to know how many users visit the portal, how far they progress through registration, and where they abandon the process.

### Required implementation

Build a portal analytics module with registration funnel tracking.

### A. Portal visitor analytics

Track:
- Total portal visits.
- Unique visitors.
- Total registration attempts.
- Successfully completed registrations.
- Returning visitors, where technically measurable.
- Daily, weekly, and monthly statistics.

### B. Registration funnel tracking

Identify and track each registration step.

First, inspect the actual registration workflow and identify its steps. Do not assume the number or names of steps.

For each step, track:
- Number of users who reached the step.
- Number of users who completed the step.
- Number of users who abandoned the process.
- Conversion rate.
- Drop-off rate.

Example funnel:

1. Portal visit.
2. Registration page opened.
3. Personal/business information submitted.
4. Required documents uploaded, if applicable.
5. Additional registration information completed.
6. Final registration submission.
7. Account successfully created.

The example above is illustrative. Adapt it to the actual registration process.

### C. Analytics dashboard

Provide:
- Summary statistics cards.
- Registration funnel visualization.
- Conversion and abandonment percentages.
- Date-range filters.
- Daily/weekly/monthly charts.
- Step-by-step breakdown.
- Comparison between selected periods, if supported by the existing architecture.

### D. Technical considerations

- Implement reliable event tracking.
- Avoid counting page refreshes as new unique visitors.
- Use sessions or privacy-conscious anonymous visitor identifiers where appropriate.
- Support authenticated and unauthenticated visitors.
- Prevent duplicate funnel events where possible.
- Avoid storing unnecessary personal information.
- Optimize database queries and add appropriate indexes.
- Make tracking asynchronous where appropriate to avoid slowing down portal performance.

The analytics must reflect actual tracked activity, not simulated or hardcoded numbers.

---

## Requirement 3: Global Contracts with Optional Product-Specific Contracts

### Business problem

Currently, the client does not want **sellers (vendors)** to sign a separate contract for every product.

> Terminology note: the client's Arabic word «العميل» means both "client" and "customer". In this requirement it refers to the **seller who lists products** (the example is licensed products — food and blends — which sellers list). Customer-side purchase contracts are Requirement 7. Do not implement this requirement for storefront customers.

The desired default behavior is that one contract covers all applicable products.

However, certain products, such as food products and mixtures, may require special licenses and therefore need an individual contract.

### Required implementation

Redesign or extend the existing contract assignment logic.

Introduce a configurable contract policy that supports:

**Mode A – Global contract (default)**

- The seller signs one general contract (the platform Seller Agreement, which already exists — reuse it).
- The signed contract covers all products that do not explicitly require individual contracts.
- The seller should not be asked to sign the same general contract repeatedly for each product.

**Mode B – Product-specific contract**

- Individual products can be configured to require their own contract.
- This setting must be configurable independently for each product.
- When enabled, the seller must sign the applicable product-specific contract before the product can be listed/activated.
- When disabled, the product follows the global contract policy.

### Product configuration

Add a setting to product management:

`Requires Individual Contract: Yes / No`

Also support the necessary contract template or contract assignment for the product.

### Important business rules

1. Global contracts should be the default behavior.
2. Product-specific contracts are exceptions, not the default.
3. A product requiring an individual contract must not be considered covered by the global contract alone.
4. A seller should not be forced to sign duplicate contracts.
5. Contract validity, acceptance, versioning, and signing history must be maintained.
6. Existing signed contracts must not be silently invalidated by this change.
7. Validate the contract requirements before allowing the relevant business action or product activation, according to the existing workflow.

Design this carefully to support future expansion.

---

## Requirement 4: Country-Based Contracts

### Business problem

Contracts must differ depending on the country.

### Required implementation

Introduce country-aware contract management.

Administrators must be able to:

1. Create contracts associated with specific countries.
2. Edit and manage contract templates independently for each country.
3. Define the default contract for a country.
4. Support global contracts and product-specific contracts within each country.
5. Activate/deactivate contracts.
6. Manage contract versions and effective dates.

### Contract selection logic

The system must automatically determine which contract applies based on the **contracting party's country**: the seller's country for seller contracts, the advertisement/listing country for customer purchase contracts, and the marketer's country for marketer contracts.

Expected priority:

1. Check the contracting party's country.
2. Find the active contract configured for that country.
3. Determine whether the product requires an individual contract.
4. If yes, retrieve the applicable product-specific contract for that country.
5. Otherwise, use the applicable global contract for that country.

Do not silently fall back to another country's contract when a country-specific contract is missing.

Provide a clear validation message if the required contract is not configured.

Ensure the solution supports future countries without requiring code changes.

---

## Requirement 5: Automatic Classified Advertisement Specialization

### Business problem

When creating contracts for classified advertisements, the contract should automatically be associated with its relevant specialization or category.

Examples:
- Contracts.
- Real Estate.
- Travel.
- Vehicles.
- Other classified advertisement categories.

### Required implementation

Review the existing classified advertisements module and its category structure.

Implement a relationship between contracts and classified advertisement categories/specializations.

Expected behavior:

1. When an administrator creates or configures a contract for a classified advertisement category, the system should automatically associate it with the relevant category.
2. The association should be maintained through database relationships, not hardcoded category names.
3. Contracts must be filterable by classified category.
4. When users browse or submit classified advertisements, the relevant contract should be determined from the advertisement category.
5. Avoid mixing unrelated contracts across categories.

If the existing system has nested categories, support the appropriate hierarchy and inheritance behavior.

---

## Requirement 6: Contracts for Influencers, Celebrities, and Brokers

### Business problem

Contracts related to sellers working with influencers, celebrities, brokers, or intermediaries must appear in the relevant person's profile.

The client also needs to control where contracts are displayed.

### Required implementation

Extend contract management with a configurable display destination.

Introduce a field similar to:

`Display On`

Supported destinations should be database-driven and may include:

- General contracts section.
- Influencer profile.
- Celebrity profile.
- Broker/intermediary profile.
- Seller profile.
- Other relevant destinations supported by the existing system.

Do not assume all destinations are separate entity types. Inspect the existing database and determine the correct relationships.

### Expected behavior

1. When creating or editing a contract, the administrator can select where that contract should appear.
2. Contracts configured for influencer profiles must appear in the relevant influencer profile.
3. Contracts configured for broker/intermediary profiles must appear in the relevant profile.
4. Contracts should be linked to the correct entity or entities.
5. Profiles must display only applicable contracts.
6. Add filtering capabilities within influencer and broker profiles.

### Profile filtering

Provide filters such as:
- Contract type.
- Classified advertisement category.
- Country.
- Contract status.
- Effective date, where applicable.

The implementation must support a profile having multiple associated contracts.

Ensure the contract listing is properly authorized and does not expose private or internal contracts to unauthorized users.

---

## Requirement 7: Customer Contracts for Purchasing Products and Assets

### Business problem

Customers may need to sign contracts when purchasing certain items, such as:

- Real estate.
- Cars.
- Other classified products or assets.

These contracts are different from seller contracts or influencer collaboration contracts.

### Required implementation

Support customer-facing contracts associated with classified advertisements and purchase transactions.

Expected workflow:

1. A customer browses a classified advertisement.
2. The customer selects an item, such as a property or vehicle.
3. The system determines whether a customer contract is required.
4. The system retrieves the applicable contract based on the country, advertisement category, and configured contract rules.
5. The customer reviews the contract.
6. The customer accepts/signs the contract.
7. The system records the signed contract and links it to the appropriate customer and advertisement/transaction.
8. The customer can access their signed contracts through the appropriate account section.

### Important distinctions

Keep separate business concepts for:

- Seller contracts.
- Influencer/celebrity collaboration contracts.
- Broker/intermediary contracts.
- Customer purchase contracts.

Avoid treating every contract as the same workflow simply because they share a contract template.

Use a flexible contract type/role configuration with clear business relationships.

### Signing and auditing

Preserve:
- Customer identity.
- Related advertisement.
- Related product or asset.
- Contract template and version.
- Acceptance/signature timestamp.
- Contract status.
- Relevant audit information.

Do not claim legal validity for an electronic signature unless the existing implementation provides the required legal and technical guarantees.

---

## Requirement 8: Dynamic Classified Advertisement Categories and Dedicated Website Sections

### Business problem

The client wants to manage classified advertisement categories, such as real estate and other classifications, and display them in dedicated locations on the website.

### Required implementation

Build or extend dynamic classified advertisement category management.

Administrators must be able to:

1. Create new classified categories.
2. Edit existing categories.
3. Delete or deactivate categories, subject to existing data relationships.
4. Define category names in supported languages.
5. Configure category slugs.
6. Configure parent/child category relationships if supported.
7. Assign icons and images where appropriate.
8. Control category visibility.
9. Configure where a category appears on the website.
10. Control display order.

### Dedicated website sections

Examples:
- Real Estate.
- Vehicles.
- Travel.
- Services.
- Other classified categories.

The frontend should dynamically render dedicated sections based on configured categories.

Requirements:

- Administrators must not need developer intervention to add a new category.
- Categories should be displayed in their designated website sections.
- Each section should show relevant advertisements only.
- Support category-specific landing pages where consistent with the current architecture.
- Ensure responsive UI.
- Preserve SEO-friendly URLs and metadata.
- Do not hardcode category names or sections in frontend components.

If a category is disabled, ensure its advertisements and existing historical records are handled safely without destructive data loss.

---

## Requirement 9: Credential / Password Email Delivery

### Business problem

The client asks: *must the email address belong to the control panel's own mail domain?* They created a mailbox to use, and the **password email never arrived**.

### Required implementation

1. Trace every flow that emails a password, temporary password, or credentials to a user (seller approval, employee/admin creation, marketer/partner onboarding, password reset, registration completion). Identify the mail classes/notifications used.
2. Verify the mail configuration and delivery path: `config/mail.php`, `.env.example`, queue usage (is the mail queued, and is a queue worker configured in the deployment/process-manager files?), hard-wired `from` address/domain.
3. Confirm and document whether anything in the code requires the recipient address to share the panel's domain. If the code enforces such a rule, remove it. Any email address must be accepted.
4. Failures must not be swallowed. If sending fails, the admin must see a clear error/warning and the failure must be logged with the recipient and reason (no password in logs).
5. Provide a recovery path: an admin **"Resend credentials"** action and/or a secure set-password/reset link; prefer a time-limited set-password link over plaintext passwords where the existing flow allows it without breaking it.
6. Add tests with `Mail::fake()` / `Notification::fake()` covering the flows, including the failure path.
7. Do not change production environment settings. List the exact `MAIL_*` variables and the queue worker the administrator must verify in the final report.

---

## Requirement 10: Portal Country Selection Must Persist Across Pages

### Business problem

Intermittent seller-portal bug: on the portal home page the visitor selects **Oman** and chooses products, then presses **Continue**. The next page shows details of **UAE or Egypt** instead of Oman. The visitor has to select Oman again on that page before Oman's details appear.

### Required implementation

1. Trace how the portal resolves the current country end to end (route `{country}` segment, session, IP/Accept-Language fallbacks, country selector component/JS, middleware, redirects, generated links, caching including per-country content cache keys and any HTTP/CDN cache headers).
2. Reproduce the bug and identify the **root cause(s)** with evidence — do not guess.
3. Fix it so the country the visitor selected is carried to every next page: via the URL segment and/or server-side session persisted *before* navigation, in links, forms and redirects ("Continue" from the home page, registration entry points, nav, footer, language switch).
4. Pages that differ by country must never be served from a cache entry belonging to another country.
5. Add regression tests: select each of at least two non-default countries, follow Continue, and assert the next page renders that country's details; also cover direct links, switching country on a later page, and the language switcher keeping the country.
6. Check recent commits touching the country switcher for regressions.

---

# 3. Architecture and Database Design

Before implementation, design the database changes needed to support all requirements.

Consider the relationships between:

- Countries.
- Contract templates.
- Contract types.
- Contract versions.
- Contract display destinations.
- Products.
- Classified advertisement categories.
- Classified advertisements.
- Customers.
- Sellers.
- Influencers/celebrities.
- Brokers/intermediaries.
- Signed contracts.
- Portal analytics events.
- Registration funnel steps.
- Employee roles and permissions.

Important:

1. Reuse existing entities wherever possible.
2. Avoid unnecessary duplication.
3. Use normalized relationships.
4. Add foreign keys and indexes where appropriate.
5. Use database transactions for multi-step operations.
6. Create safe migrations with backward compatibility.
7. Ensure nullable fields and defaults are handled correctly.
8. Do not delete existing production data.
9. Add appropriate constraints to prevent duplicate or conflicting contract assignments.

Do not force all contract relationships into one generic many-to-many relationship if the existing business domain requires distinct relationships.

Choose an architecture that is extensible without becoming unnecessarily complicated.

---

# 4. Admin Panel and User Experience

All new functionality must be integrated into the existing admin panel.

Provide professional interfaces for:

- Portal permissions.
- Portal analytics.
- Registration funnel.
- Country-based contract management.
- Contract types and destinations.
- Global and product-specific contract configuration.
- Classified category management.
- Influencer/broker contract management.
- Customer contract management.

UX requirements:

- Follow the existing design system.
- Use consistent components and form patterns.
- Provide clear labels and helpful descriptions.
- Use proper validation.
- Include loading, empty, success, and error states.
- Use confirmation dialogs for destructive operations.
- Support pagination and filtering where needed.
- Respect the existing language and RTL/LTR implementation.
- Do not introduce an unrelated UI design system.

---

# 5. Security and Business Logic

This is a production application.

Ensure:

- Backend authorization on every protected operation.
- Proper validation of all user inputs.
- Protection against unauthorized contract access.
- Secure handling of customer and employee data.
- Audit logging for important contract and permission changes.
- No sensitive information in analytics events.
- Protection against duplicate contract signing.
- Proper handling of contract versions.
- Safe handling of concurrent requests.
- No frontend-only business logic.

Never trust frontend-supplied country, user, product, contract, or category identifiers without backend validation.

---

# 6. Testing and Quality Assurance

Implement tests for all important business scenarios.

At minimum, test the following:

### Permissions
- Employee with portal view permission.
- Employee with portal edit permission.
- Employee without portal permissions.
- Unauthorized backend requests.

### Analytics
- Unique visitor tracking.
- Registration funnel progression.
- Registration abandonment.
- Duplicate event prevention.
- Date-range filtering.

### Contracts
- One global contract covers applicable products.
- Products with individual contracts require separate signing.
- Products without individual contracts do not require duplicate signing.
- Different countries receive different contracts.
- Missing country contract produces a clear validation error.
- Contract versions preserve existing signatures.
- Seller and customer contracts remain distinct.
- Influencer and broker profiles show the correct contracts.
- Contract filters return correct results.

### Classified advertisements
- Dynamic category creation.
- Parent/child category behavior.
- Category-based contract selection.
- Dedicated frontend sections.
- Disabled category handling.

Run the existing test suite and all relevant new tests.

Fix regressions introduced by your changes.

Do not disable or weaken existing tests to make the implementation pass.

---

# 7. Execution Strategy

Follow this execution sequence:

**Phase 1 – Codebase Discovery**
- Inspect architecture and existing modules.
- Map all relevant workflows.
- Identify dependencies and integration points.
- Document current implementation gaps.

**Phase 2 – Technical Design**
- Define the required database changes.
- Define relationships and business rules.
- Identify backend and frontend files that need modification.
- Prepare an implementation checklist.

**Phase 3 – Backend Implementation**
- Migrations.
- Models and relationships.
- Services and business logic.
- Permissions.
- APIs/controllers.
- Validation.
- Events and analytics tracking.
- Contract selection and signing logic.

**Phase 4 – Frontend Implementation**
- Admin interfaces.
- Employee permissions UI.
- Analytics dashboard.
- Contract management UI.
- Customer-facing contract workflows.
- Influencer/broker profile integration.
- Dynamic classified categories and website sections.

**Phase 5 – Testing and Optimization**
- Automated tests.
- Integration tests.
- Authorization checks.
- Database query optimization.
- Regression testing.
- UI/UX review.

**Phase 6 – Final Verification**
- Verify all ten requirements.
- Confirm existing features still work.
- Confirm migrations are safe.
- Confirm there are no broken routes, missing permissions, or frontend errors.

---

# 8. Mandatory Working Rules

1. Do not stop after analyzing the codebase. Continue to actual implementation.
2. Do not just generate a plan or documentation and consider the task complete.
3. Do not create mock interfaces without functional backend integration.
4. Do not use dummy data in production features.
5. Do not leave TODO comments or placeholder methods for required functionality.
6. Do not rewrite unrelated parts of the project.
7. Do not make unnecessary architectural changes.
8. Reuse existing services, components, and conventions.
9. Keep changes modular and maintainable.
10. Run relevant tests after implementation.
11. If you encounter an ambiguous business rule that could cause incorrect behavior, inspect the existing system first. If it cannot be resolved safely, document the exact ambiguity rather than inventing a destructive business rule.
12. Work incrementally and verify each phase before proceeding.
13. Do not overwrite unrelated uncommitted user changes. Inspect Git status before modifying files.
14. Do not run destructive production database commands. This is a live project: **never delete, rename, roll back, or edit an existing migration file**, and never run `schema:dump --prune` or `migrate:fresh/reset`, `db:wipe`. Only add new, additive migrations.
15. Do not modify production environment settings or deploy without explicit authorization.

### Token and Context Efficiency

Work efficiently:
- Avoid repeatedly reading the same files.
- Use targeted searches to locate relevant code.
- Parallelize independent analysis tasks when useful.
- Avoid creating unnecessary documentation or duplicated abstractions.
- Maintain a concise implementation checklist and update it as tasks are completed.

---

# 9. Final Delivery Report

When finished, provide a clear technical report containing:

1. Summary of implemented features.
2. Database migrations created.
3. Backend files/modules modified.
4. Frontend pages/components modified.
5. New permissions and roles.
6. New APIs and routes.
7. Contract business logic and selection priority.
8. Analytics events and funnel implementation.
9. Tests executed and their results.
10. Any remaining issues or unresolved business decisions.
11. Any required environment variables or scheduled jobs.
12. Any manual configuration required by the administrator.

For every requirement, mark it as:
- COMPLETED
- PARTIALLY COMPLETED
- BLOCKED

Only mark something COMPLETED if the feature is fully implemented, integrated, and verified.

**Final objective: Deliver a fully functional, production-ready implementation of all ten client requirements, integrated cleanly into the existing application without breaking its current functionality.**

Start by inspecting the repository and Git status, then proceed through the implementation phases. Do not stop at the planning stage.
---

# 10. Audit Findings (verified against the code, 2026-11-04) and Work Packages

Stack: Laravel 13 / PHP 8.3 in `backend/` (Blade admin `routes/admin.php`, seller portal `routes/portal.php`, seller dashboard `routes/partner.php`, vendor API `routes/api_vendor.php`, customer API `routes/api_customer_v1.php`, PHPUnit on MySQL/MariaDB), Next.js 16 storefront in `frontend/` (read `frontend/AGENTS.md` and `node_modules/next/dist/docs/` before touching it). Permissions: spatie/laravel-permission, guard `admin`, middleware `admin.permission:<name>`.

## Status per requirement (before this work)

| # | Requirement | Status | Key evidence |
|---|---|---|---|
| 1 | Staff permission on portal content | PARTIAL | `portal_content.view/edit` exist and are enforced (`routes/admin.php:1252-1258`, `PortalContentController`). No grant migration; `RolesAndPermissionsSeeder.php:114` `syncPermissions` wipes custom grants on system roles at every deploy; group label missing in `RoleController` `$groupLabels` (shows "Portal_content", English-only labels); hard-coded sidebar link `layouts/partials/sidebar.blade.php:88-93` has no permission check; no create/delete/publish/manage permissions; no per-employee grant (`AdminController.php:238`). |
| 2 | Portal visitor + registration funnel stats | MISSING | No tracking at all. Registration steps: `RegistrationController` steps 1-3 post to `storeStep` (session only), step 4 documents (client-side → `uploadDocument`), step 5 review → `complete()` (only place a Vendor is created). Abandoned applicants leave no trace. Model the admin page on `AnalyticsController`/`AnalyticsService`/`admin/analytics/index.blade.php`. |
| 3 | One global seller contract + optional per-product/category contract | PARTIAL | Platform Seller Agreement exists and is signed once (`SellerAgreementService`, registration step 5, middleware `vendor.agreement.enforce`). Per-category contract is implicit via `categories.contract_template_id`; no explicit `requires_contract` switch, no product-level override, no parent-category inheritance in `CategoryContractService::templateFor`, vendor API `Vendor/ListingController::store` → `ListingService::create` is NOT gated (Flutter hole). |
| 4 | Contracts by country | MISSING | No country dimension on `classified_contract_templates` / `vendor_contracts`; `SellerAgreementService::retireOthers` retires every platform template on publish; registration step 5 and public terms page ignore the country. |
| 5 | Classified contract auto-specialised by section | PARTIAL | Only category→template FK (`classified_categories.contract_template_id`); template form has no section field; no parent inheritance; seeder has one generic template. |
| 6 | Seller↔celebrity/broker/influencer contracts, "where shown" field + filters | MISSING | `marketer_jobs` define types; `MarketerContract` is one onboarding contract per marketer; `ExclusiveContract` is shown in admin marketer profile; no `contract_type`/display-destination on templates, no seller-with-marketer contract, no contract filters on `Admin\MarketerController@index`; orphan `Marketer\ContractController` returns a non-existent view. |
| 7 | Customer contracts for property/car purchase | MISSING | Classified "purchase" is only a `ClassifiedInquiry`; no customer contract table/service/API/UI. Reuse the pdf/text modal pattern of `frontend/src/features/noon/checkout/marketer-contract-modal.tsx`. |
| 8 | Admin-managed classified categories in dedicated site places | PARTIAL | CRUD exists (`Admin\ClassifiedCategoryController`, storefront nav/tree resources, Next.js `classified/*` pages). Missing: country scope, image upload (icon is a 50-char string), explicit "where it appears" placement, and verification of disabled-category handling. |
| 9 | Password email not arriving | CAUSES FOUND | Nothing requires the recipient to share the panel's domain. Causes: `MAIL_MAILER=log` default (`.env.example`, deploy `full-install.sh`); queued mails need the PM2 `mp-queue` worker; `AdminController::resetPassword` (L318-335) discards the new password, sends nothing and reports success; admin staff creation returns the password in JSON, no mail; `VendorApplicationController::approve` L322-338 creates a duplicate owner with an unknown random password (unique email → rollback risk); vendor team invite sends plaintext password synchronously without try/catch; `RegistrationController` L447-450 swallows mail errors; seller forgot-password uses broker without a resolvable `password.reset` route (verify); no test-send/warning when `mail_mailer=log`. |
| 10 | Wrong country on next portal page | ROOT CAUSE FOUND | `Country::resolveFromIp/resolveFromAcceptLanguage` compare 2-letter codes to 3-letter `site_code` (om≠omn) so detection never matches; `resolveSiteCode()` then picks an unordered launched country (uae/egy). `JoinService::headerCountry()` defaults to Oman for the header while `PortalContent::resolveCountryCode()`/views use `resolveSiteCode(null)`; the fallback is never written to `session('portal_country')`; `JoinController::select()`/`JoinService::resolve()` redirect without carrying the country. Also: no `trustProxies`, empty geo result cached for a day, stray `tests/Feature/Portal/TmpDebugTest.php`. |

## Work packages (run one at a time, in this order; each ends with its tests green before the next starts)

- **WP1 – Requirement 10** (country persistence). Fix tasks: match detection on `iso_code_2`; single `Country::defaultSiteCode()` used by `resolveSiteCode`, `JoinService::headerCountry`, `PortalContent::resolveCountryCode`; `SetPortalCountry` middleware on the portal group (route segment → session → detection → default, always written to session); `JoinController::select` persists country; validate `/country/{country}`; `trustProxies`; short TTL for empty geo results; remove `TmpDebugTest.php`; regression tests.
- **WP2 – Requirement 9** (credential email). Fix the concrete defects listed above; set-password links instead of plaintext; no swallowed failures; admin "resend credentials"; mail-settings "send test email" + warning banner when mailer is `log`; fix forgot-password routes; add tests; list operator checklist (`MAIL_*`, `QUEUE_CONNECTION`, `mp-queue` worker, SPF/DKIM) in the final report. Do not touch production env.
- **WP3 – Requirement 1** (permissions). New additive migration creating `portal_content.{view,create,edit,delete,publish,manage}` (+ `portal_analytics.view` for WP4), granted to `super_admin`, cached permissions cleared; add to `PermissionSeeder`; stop `RolesAndPermissionsSeeder` from wiping customised role grants; translate labels + add group labels (en/ar); gate the sidebar link; enforce the new permissions per action in the controller; tests (view-only, edit, none, 403).
- **WP4 – Requirement 2** (analytics + funnel). Tables, tracking middleware + registration step events (including the client-side step 4→5 transition), admin `PortalAnalyticsController` page (KPIs, date + country filters, daily/weekly/monthly chart, funnel table with conversion/drop-off, export), permission `portal_analytics.view`, nav item, en/ar, retention command, tests. Respect privacy (hashed IP, no PII).
- **WP5 – Requirements 4 + 3** (country-aware contracts, global vs per-product). Country dimension on templates and signatures (null = legacy/global), per-country version lineage and default flag, a `ContractResolver` with the priority in Requirement 4 and an explicit "not configured for country X" error (no silent fallback), country-scoped `SellerAgreementService`/`CategoryContractService`, registration step 5 + public page by the seller's country, explicit `requires_contract` switch on categories + product-level override (inherit/required/exempt) + parent-category inheritance, enforcement in web, API (`Vendor\ListingService::create`) and activation, admin UI + filters, en/ar, tests.
- **WP6 – Requirements 5 + 8** (classified sections + categories). Template↔classified-category association via DB relation (auto-link, parent inheritance, default per section, filter by category in admin), per-section seeded templates (idempotent); category country scope, image upload, placement (`show_on_home`/section placement), disabled-category safety, storefront rendering from configuration; tests.
- **WP7 – Requirement 6** (contracts in celebrity/broker/influencer profiles). `contract_type` + `Display On` destinations (database-driven), seller↔marketer contract template + signing record linked to the marketer, contract panel in admin marketer profile and marketer panel (fix the missing view), filters (type, classified category, country, status, effective date) in marketer lists/profiles, authorization, tests.
- **WP8 – Requirement 7** (customer purchase contracts). `customer_contracts` (separate concept from seller/marketer contracts), service, customer API + storefront Next.js modal + "my contracts" account section, gating of the inquiry/purchase flow when a template applies (country + category), admin management/list, audit data, tests. No legal-validity claims.
- **WP9 – Final QA.** Independent verification of all ten requirements against this file, full relevant test run, `vendor/bin/pint`, route/permission/translation checks, migration safety review (`git diff` shows only new migration files), frontend lint/type-check, then the Final Delivery Report (Section 9) with COMPLETED / PARTIALLY COMPLETED / BLOCKED per requirement.

Working rules for every WP: follow Sections 8 and 5 above; do not commit; additive migrations only; run `vendor/bin/pint --dirty --format agent` on changed PHP; run the narrowest relevant tests and report real results; report files changed, migrations, permissions, routes, test output and any unresolved decision.
