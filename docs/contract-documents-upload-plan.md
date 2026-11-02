# Contract documents: upload mode + full document lifecycle

Status: execution plan. Execution order is the checklist at the bottom; each item is ticked when done and verified.

## 1. Sources analysed

| Source | What it gives us |
|---|---|
| `المستند-6.pdf` | The **lifecycle flow** the platform must follow (see §2). |
| `التحديثات-الاخيرة-عقد-تجار-المنتجات.pdf` (34 pages, Arabic, "Seller Agreement v1, effective 1/11/2026") | The **example document an admin will upload**: one platform-wide agreement with appendices A–H. Not category-specific, Arabic is the official language (art. 24), no `{{variables}}`. Hence it must be uploadable as a file, and it must be signable once per vendor, not once per category. |
| Existing code | `ContractTemplateController` (admin, `/contracts/templates`), `CategoryContractService`, `ClassifiedContractTemplate`, `VendorContract`, partner views `partner/contracts/*`, vendor API `ContractController`, portal `RegistrationController` (step 5 only has an unpersisted "I agree" checkbox), marketer contracts (already have a pdf/text switch, used as the pattern). |

### Panel mapping (what "portal | partner | admin" means here)

| Panel | Code | Role in the flow |
|---|---|---|
| **Portal** (seller site, `routes/portal.php`) | `Portal\RegistrationController`, `portal/register/step-5` | Document 1 §1: the seller signs the agreement during registration, before the account is submitted. Also hosts the public "current terms" page (doc 1 §3). |
| **Partner** (seller dashboard, `routes/partner.php`) | `Partner\VendorCategoryContractController` + new legal pages | Doc 1 §2: *Settings → Legal Documents* (view/download signed copy), forced click-wrap on login for new versions, notice banner for routine changes. |
| **Admin** | `Admin\ContractTemplateController` | Upload or write templates, publish with an amendment mode, see signatures. |

Not touched: the Next.js customer frontend (only the marketer contract modal, which already supports pdf/text) and the Flutter partner app (no contract UI exists). The vendor **API** gets additive fields/endpoints only (no hard blocking, because the app has no screen to accept).

## 2. Flow required by `المستند-6.pdf`, mapped to implementation

| # | Requirement in doc | Implementation |
|---|---|---|
| 1 | Signing happens on the seller site during registration, before activation. Click "I agree" or type full name. Cannot submit without it. | Step 5 of portal registration shows the current Seller Agreement (embedded PDF / HTML), requires **typed full name + checkbox**. `complete()` rejects without them when an agreement is published, and writes a `vendor_contracts` row (`acceptance_method = registration`) in the same transaction as the vendor. |
| 2 | Signed contract is kept in dashboard under Settings → Legal Documents, viewable/downloadable. | New partner page `partner.legal.index` with the agreement in force, signature details, download of the signed copy, history, and category contracts. |
| 3 | Copy of the agreement emailed automatically after signing. | `SellerAgreementSignedMail` (queued) with the signed file attached (or the frozen text inline for editor mode). `vendor_contracts.emailed_at` records it. |
| 4a | Major changes: click-wrap pop-up blocks the dashboard until "Accept". | Middleware `vendor.agreement.enforce` on the partner panel redirects to the acceptance gate until the vendor accepts the new version (JSON requests get `423`). Publish mode **click_wrap**. |
| 4b | Routine changes: email notice N days before effect (e.g. 14), continued use = implicit acceptance. | Publish mode **notice** (+ `notice_days`, default 14). Publish emails/notifies vendors with the effective date and a change summary. After `effective_at`, `contracts:apply-effective` (scheduled) and a lazy check record an **implicit acceptance** (`acceptance_method = implicit`) so a legal record exists. Notice mode is only offered for the platform agreement. |
| 5 | Current version always available in dashboard and on a public terms page. | Partner Legal Documents + public `portal.legal.seller-agreement` page (+ file route). Register step 5 terms link points to it. |
| (art. 5.6 of the contract) | Previous versions archived. | Templates are never deleted once published/signed; retired versions stay and signed copies are frozen per vendor. |

## 3. Design

### 3.1 Template: two content modes (switch)

`classified_contract_templates` gets:

- `content_mode` enum(`editor`,`file`) default `editor`
- `files` json: `{ "ar": {path,name,mime,size,sha256,pdf_path}, "en": {...} }` (either language may be absent; Arabic is official, the other falls back to it)
- `category_scope` enum extended with **`platform`** (platform-wide Seller Agreement)
- `acceptance_mode` enum(`click_wrap`,`notice`) default `click_wrap`, `notice_days` smallint default 14, `effective_at` timestamp, `published_at` timestamp, `change_summary` text
- `content_en/content_ar` stay NOT NULL; file mode stores `''`.

Files live on the **private** disk under `contract-templates/{templateId}/…`, served only through authenticated controllers (admin / partner / public-for-published-platform). Accepted: `pdf, doc, docx`, max 20 MB, sha256 stored. `.docx/.doc` are converted to a PDF preview with LibreOffice headless when available (`ContractFileService`); if conversion is unavailable the original stays downloadable and viewers show a download button instead of an inline preview. Never blocks upload.

Editing rules are unchanged: drafts edit in place; a published template is frozen, and a change (content, mode or file) creates a **new draft version** (files are copied into the new version's folder so deleting a draft never affects another version).

### 3.2 Signature record

`vendor_contracts` gets: `category_scope` + `platform`, `vendor_category_enrollment_id` nullable (platform signatures have no enrollment), `content_mode`, `acceptance_method` enum(`registration`,`click_wrap`,`implicit`), `signed_file_path/name/mime` (frozen private copy of the exact file signed), `emailed_at`. For file mode `rendered_content` is `''` and `rendered_content_hash` = sha256 of the file. Existing rows keep default `click_wrap`.

### 3.3 Services

- `Services\Vendor\ContractFileService`: store/validate/convert/copy/delete files.
- `Services\Vendor\SellerAgreementService` (platform scope): `inForce()`, `upcoming()`, `pendingFor($vendor)`, `sign(...)`, `recordImplicitAcceptance(...)`, `publish($template)` (click-wrap retires the old version now; notice keeps it until `effective_at`), `applyEffective()`.
- `CategoryContractService`: gains a `presentTemplate()` helper that returns `content_mode`, rendered text and file URLs, reused by every consumer; `sign()` also freezes the file for file-mode category templates.
- Single lineage for the `platform` scope (all platform templates are versions of one agreement regardless of name).

### 3.4 Surfaces

**Admin**
- Form: segmented switch *Write in editor | Upload file*, per-language file inputs showing the current file, size, hash; scope list adds *Seller Agreement (platform)*; for platform: acceptance mode, notice days, change summary.
- Index: mode badge, `scheduled` status, effective date; file preview/download route; signatures page works for platform scope (all vendors); all-signatures list shows scope/method and lets admin download the signed copy.

**Partner**
- `partner.legal.index` (Legal Documents), `partner.legal.agreement` + `accept` (gate), `partner.legal.template-file`, `partner.legal.signed-file`; sidebar link; pending/preview/signed views render file mode through a shared viewer partial; notice banner on dashboard layout when an upcoming version exists.

**Portal**
- Register step 5 viewer + typed name; `complete()` signs; public terms page; copy email.

**API (vendor)** additive: `GET legal/agreement`, `POST legal/agreement/accept`, `GET legal/documents`; existing pending/check-category/listing payloads gain `content_mode` and `files`. Classified listing wizard payloads get `contract_template_file_url` for file templates.

### 3.5 Rules and risks

- Additive migrations only (project rule); no destructive DB commands. `enum` change and nullable FK column via new migrations.
- Nothing changes for vendors until an admin **publishes** a platform template, so enforcement is safe to deploy.
- Authorization: vendors can only fetch published templates and their own signed copies (404 otherwise).
- Risk: mobile API is not blocked by the gate (no UI there); listed as follow-up.
- Risk: LibreOffice may be missing in production → graceful fallback described above.

## 4. Test plan

PHPUnit feature tests (MySQL, `RefreshDatabase`), using the real example PDF as fixture and a generated docx:

1. Admin: create file-mode draft (pdf), validation (no file, wrong type, oversize), editor mode still works, mode switch, editing published → new version with copied files, publish, file download authorization, delete removes files.
2. Platform lifecycle: registration without signature → 422; with signature → vendor + signature + mail queued + frozen copy; gate redirects until accept; click-wrap new version re-gates; notice mode does not gate and then becomes implicit acceptance after `effective_at` (command + lazy); Legal Documents page and downloads (other vendor → 404); public terms page.
3. Category contract in file mode: pending/accept/signed copy; API payloads.
4. Existing `CategoryContractTest` still green.
5. Browser/HTTP smoke of the real flow with the example PDF on the dev server.

## 5. Execution checklist

- [x] M1 migrations (templates, vendor_contracts) + run on dev + models
- [x] ContractFileService
- [x] Admin controller/form/index/signatures + routes (file route, signed download)
- [x] SellerAgreementService + publish/notice + command + schedule + notifications/mails/job
- [x] CategoryContractService presenter + file-mode signing
- [x] Middleware + partner legal pages + sidebar + updated pending/preview/signed views
- [x] Portal registration step 5 + complete() + public terms page
- [x] Vendor API + classified wizard payloads
- [x] Translations en/ar (admin, partner, portal, mail, notifications)
- [x] Tests + pint
- [x] Real-file smoke run, final report

## 6. Result notes

- Tests added: `tests/Feature/Admin/ContractTemplateUploadTest.php` (14), `tests/Feature/Vendor/SellerAgreementLifecycleTest.php` (16), fixtures in `tests/Support/ContractDocumentFixtures.php`. Existing `CategoryContractTest` still passes.
- The real example agreement (34 pages) was pushed through upload, publish, public page, registration signing and signed-copy download; the signed copy is byte-identical (sha256) to the source.
- Tests point the private disk at a temp directory because `storage/framework/testing/disks` is not writable by this user (owned by another account).
- Follow-ups not done: hard gate for the Flutter vendor API (no accept screen there), file mode for marketer/travel-agency registrations, storefront customer classified contract text.
