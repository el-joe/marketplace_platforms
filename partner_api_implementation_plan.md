# Partner API Implementation Plan
**Author:** dev.youssefmagdy@gmail.com  
**Date:** 2026-10-03  
**Branch:** main

---

## 1. Business Logic Breakdown

### 1.1 Current State

The system already has a **Partner Mobile API** at `/api/partner/v1/...` backed by JWT (`vendor_api` guard, tymon/jwt-auth). It is read-only and covers: auth, dashboard, notifications, orders, returns, warranty claims, listings, product custom attributes, inventory, warehouses, classifieds, performance, and finance.

The **Partner Panel** (web, session-based `vendor` guard) is far richer: it also covers coupons, flash sales, ads, ad-bookings, fulfillment, packaging supplies, payment methods, payouts, claims, disputes, team/roles, profile/change-requests, subscriptions, support tickets, bank accounts, city surcharges, and AI tools.

### 1.2 What Needs to Be Added

| # | Area | Change |
|---|------|--------|
| A | External Partner API Token | New `vendor_api_tokens` table; no-expiry opaque tokens tied to `VendorAdmin`; custom guard/middleware |
| B | Dual-auth middleware | Mobile uses JWT; External Partners use Bearer `vnd_` token; single middleware resolves both |
| C | API expansion (write ops) | Expose Partner Panel write operations (order lifecycle, listing CRUD, inventory, coupons, etc.) as versioned API endpoints under `/api/partner/v1/` |
| D | Partner Panel – API Docs page | Blade view listing all endpoints with descriptions, request/response examples |
| E | Partner Panel – Token Manager | Blade UI to generate, label, copy, and revoke permanent API tokens |

### 1.3 Auth Flow Summary

```
Mobile App (Flutter)
  └─ POST /api/partner/v1/auth/login  →  JWT (existing vendor_api guard)
  └─ Bearer <jwt_token>  →  VendorApiAuth middleware (existing)

External Partner (3rd-party)
  └─ GET /partner/developer/tokens  →  generate token in Partner Panel UI
  └─ Bearer vnd_<random64>  →  new VendorTokenAuth middleware
  └─ token looked up in vendor_api_tokens, authenticated as VendorAdmin
```

Both consumers share the same `/api/partner/v1/` route group via a **unified dual-auth middleware** that tries JWT first, then falls back to static token lookup.

---

## 2. Database / Migration Requirements

### 2.1 New Table: `vendor_api_tokens`

```sql
CREATE TABLE vendor_api_tokens (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_admin_id CHAR(36) NOT NULL,               -- UUID FK → vendor_admins.id
    name            VARCHAR(100) NOT NULL,            -- human label ("Production Key")
    token           VARCHAR(128) NOT NULL UNIQUE,     -- hashed SHA-256; plain only shown once
    token_prefix    VARCHAR(8) NOT NULL,              -- first 8 chars for display (e.g. "vnd_Ab1c")
    last_used_at    TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    FOREIGN KEY (vendor_admin_id) REFERENCES vendor_admins(id) ON DELETE CASCADE
);
```

**Notes:**
- `token` column stores a **SHA-256 hash** of the actual token. The plain-text `vnd_<64-random-chars>` is shown once at creation, never stored.
- No `expires_at` column — these are permanent by design.
- Only the `owner` or `manager` role may create/revoke tokens (enforced in controller).

### 2.2 No changes to `vendor_admins` table required.

---

## 3. Route Definitions & Middleware Strategy

### 3.1 Middleware

| Middleware class | File | Purpose |
|---|---|---|
| `VendorApiAuth` | `app/Http/Middleware/VendorApiAuth.php` | **Existing** — JWT only |
| `VendorTokenAuth` | `app/Http/Middleware/VendorTokenAuth.php` | **New** — static token lookup |
| `VendorDualAuth` | `app/Http/Middleware/VendorDualAuth.php` | **New** — tries JWT first, then token; sets `request->attributes->set('auth_method', 'jwt'|'token')` |
| `VendorApiActive` | `app/Http/Middleware/VendorApiActive.php` | **Existing** — checks vendor is active |

The existing route group in `api_partner.php` will swap `vendor.api.auth` → `vendor.dual.auth` so both consumers pass through.

### 3.2 New API Routes (additions to `api_partner.php`)

All additions live inside the existing authenticated group.

```
POST   /api/partner/v1/orders/{subOrderNumber}/confirm
POST   /api/partner/v1/orders/{subOrderNumber}/ship
POST   /api/partner/v1/orders/{subOrderNumber}/cancel
POST   /api/partner/v1/orders/{subOrderNumber}/out-for-delivery
POST   /api/partner/v1/orders/{subOrderNumber}/deliver

POST   /api/partner/v1/listings
PUT    /api/partner/v1/listings/{id}
POST   /api/partner/v1/listings/{id}/toggle-status
POST   /api/partner/v1/listings/{id}/update-price
POST   /api/partner/v1/listings/{id}/adjust-stock

POST   /api/partner/v1/returns/{returnNumber}/approve
POST   /api/partner/v1/returns/{returnNumber}/reject

GET    /api/partner/v1/coupons
POST   /api/partner/v1/coupons
GET    /api/partner/v1/coupons/{id}
PUT    /api/partner/v1/coupons/{id}
POST   /api/partner/v1/coupons/{id}/toggle-status
DELETE /api/partner/v1/coupons/{id}

GET    /api/partner/v1/support-tickets
POST   /api/partner/v1/support-tickets
GET    /api/partner/v1/support-tickets/{id}
POST   /api/partner/v1/support-tickets/{id}/replies

GET    /api/partner/v1/team
POST   /api/partner/v1/team
PUT    /api/partner/v1/team/{id}
DELETE /api/partner/v1/team/{id}

# Token management (only for JWT-authenticated owners — cannot bootstrap from a token)
GET    /api/partner/v1/developer/tokens
POST   /api/partner/v1/developer/tokens
DELETE /api/partner/v1/developer/tokens/{id}
```

### 3.3 New Partner Panel Web Routes (additions to `partner.php`)

```
GET    /partner/developer          →  index (docs + token manager)
POST   /partner/developer/tokens   →  store
DELETE /partner/developer/tokens/{id} →  destroy
```

---

## 4. UI Component Architecture

### 4.1 Partner Panel — Developer Page (`/partner/developer`)

**View:** `resources/views/partner/developer/index.blade.php`

Two-tab layout (Alpine.js, consistent with existing tabs in the panel):

**Tab 1 — API Documentation**
- Sections per resource (Orders, Listings, Inventory, Finance, etc.)
- Each section: endpoint table (method badge, path, description) + collapsible example request/response JSON
- Auth section explaining both JWT and Token methods with copy-paste curl examples

**Tab 2 — API Tokens**
- List of existing tokens (name, prefix, last used, created date)
- "Generate New Token" button → modal with `name` field → on save, shows plain-text token once with a copy button + warning
- Revoke button per token with confirmation

**Controller:** `app/Http/Controllers/Partner/DeveloperController.php`

---

## 5. Step-by-Step Sub-Agent Execution Plan

> Each task ends with a `git commit` before the next begins.

---

### Task 1 — Database Migration: `vendor_api_tokens` table
**Files:**
- `database/migrations/2026_10_03_000001_create_vendor_api_tokens_table.php`
- `app/Models/VendorApiToken.php`

**Steps:**
1. Generate migration via `php artisan make:migration create_vendor_api_tokens_table`
2. Define schema (id, vendor_admin_id FK, name, token UNIQUE, token_prefix, last_used_at, timestamps)
3. Generate model via `php artisan make:model VendorApiToken`
4. Add `belongsTo(VendorAdmin::class)` on model; add `hasMany(VendorApiToken::class)` on `VendorAdmin`
5. Run `php artisan migrate`
6. Run `vendor/bin/pint --dirty --format agent`
7. **Commit:** `feat: [Task 1] Add vendor_api_tokens migration and model`

---

### Task 2 — Dual Authentication Middleware
**Files:**
- `app/Http/Middleware/VendorTokenAuth.php` (new)
- `app/Http/Middleware/VendorDualAuth.php` (new)
- `bootstrap/app.php` — register middleware aliases
- `routes/api_partner.php` — swap middleware alias

**Steps:**
1. Create `VendorTokenAuth`: extract `Bearer vnd_*` from header, hash it, look up `vendor_api_tokens`, load `VendorAdmin` via `Auth::guard('vendor_api')->setUser()`
2. Create `VendorDualAuth`: attempt JWT guard `check()` first; if fails, delegate to token lookup; 401 if neither works
3. Register both aliases in `bootstrap/app.php` (`vendor.dual.auth`, `vendor.token.auth`)
4. In `api_partner.php`, replace `vendor.api.auth` with `vendor.dual.auth` in the authenticated group
5. Run `vendor/bin/pint --dirty --format agent`
6. **Commit:** `feat: [Task 2] Add dual-auth middleware (JWT + static token)`

---

### Task 3 — API Token Management Endpoints
**Files:**
- `app/Http/Controllers/Partner/Api/DeveloperController.php` (new)
- `routes/api_partner.php` — add developer token routes

**Steps:**
1. Create `DeveloperController` with `index`, `store`, `destroy`
2. `store`: generate `vnd_` + 64 random bytes (hex), store SHA-256 hash + prefix; return plain token once
3. `destroy`: find token by id scoped to authenticated vendor, delete; 403 if not owner/manager
4. Gate: token management routes require `jwt` auth method (cannot bootstrap new tokens from a token)
5. Add routes to `api_partner.php`
6. Run `vendor/bin/pint --dirty --format agent`
7. **Commit:** `feat: [Task 3] API token CRUD endpoints`

---

### Task 4 — Expanded Write API: Orders & Returns
**Files:**
- `app/Http/Controllers/Partner/Api/OrderController.php` — add `confirm`, `ship`, `cancel`, `markOutForDelivery`, `markDelivered`
- `app/Http/Controllers/Partner/Api/ReturnController.php` — add `approve`, `reject`
- `routes/api_partner.php`

**Steps:**
1. Delegate to the same service/action classes used by the web `OrderController` (avoid duplicate logic)
2. Return consistent `{success, message, data}` JSON envelope
3. Add routes
4. Run `vendor/bin/pint --dirty --format agent`
5. **Commit:** `feat: [Task 4] Add order lifecycle and return write endpoints`

---

### Task 5 — Expanded Write API: Listings, Inventory & Coupons
**Files:**
- `app/Http/Controllers/Partner/Api/ListingController.php` — add `store`, `update`, `toggleStatus`, `updatePrice`, `adjustStock`
- `app/Http/Controllers/Partner/Api/CouponController.php` (new)
- `routes/api_partner.php`

**Steps:**
1. For listings: reuse request validation from web `ListingController`; return `{success, data: listingResource}`
2. For coupons: full CRUD + toggle-status; scoped to authenticated vendor
3. Add routes
4. Run `vendor/bin/pint --dirty --format agent`
5. **Commit:** `feat: [Task 5] Add listing write ops and coupon CRUD API endpoints`

---

### Task 6 — Expanded API: Support Tickets & Team
**Files:**
- `app/Http/Controllers/Partner/Api/SupportTicketController.php` (new)
- `app/Http/Controllers/Partner/Api/TeamController.php` (new)
- `routes/api_partner.php`

**Steps:**
1. Support: index, store, show, reply (POST replies sub-resource)
2. Team: index, store (invite member), update, destroy — owner/manager only
3. Add routes
4. Run `vendor/bin/pint --dirty --format agent`
5. **Commit:** `feat: [Task 6] Add support tickets and team management API endpoints`

---

### Task 7 — Partner Panel UI: Developer Page (Token Manager + Docs)
**Files:**
- `app/Http/Controllers/Partner/DeveloperController.php` (new)
- `resources/views/partner/developer/index.blade.php` (new)
- `routes/partner.php` — add `/developer` routes

**Steps:**
1. Create `DeveloperController` with `index` (view), `storeToken`, `destroyToken`
2. `storeToken`: same logic as API Task 3 — generate, hash, store; flash plain token to session for one-time display
3. `destroyToken`: scoped delete; only owner/manager
4. Create Blade view with Alpine.js two-tab layout:
   - Tab 1: docs section (static, covering all API endpoints)
   - Tab 2: token table + "Generate" modal + plain-token reveal (one-time from session flash)
5. Add web routes in `partner.php` behind `vendor.auth` + `vendor.active`
6. Run `vendor/bin/pint --dirty --format agent`
7. **Commit:** `feat: [Task 7] Partner Panel developer page — docs + token manager UI`

---

### Task 8 — Navigation Link & Sidebar Entry
**Files:**
- `resources/views/partner/partials/` — sidebar partial (whichever file contains the sidebar nav)

**Steps:**
1. Find the sidebar partial
2. Add "Developer / API" nav item with a code/terminal icon, pointing to `route('partner.developer.index')`
3. Run `vendor/bin/pint --dirty --format agent`
4. **Commit:** `chore: [Task 8] Add Developer API link to Partner Panel sidebar`

---

## 6. Security Considerations

- Tokens stored as SHA-256 hash; plaintext never persisted.
- Token generation scoped to `is_owner` or `manager` role only.
- Token auth middleware always resolves to a `VendorAdmin` — existing `VendorApiActive` check still runs.
- Write endpoints enforced via `vendor.can:*` permission checks (Spatie, same as web panel).
- Token management via API itself requires JWT auth method to prevent token-bootstrap loop.
- Rate limiting: existing `throttle:10,1` on login; add `throttle:60,1` on token-generation endpoint.

---

## 7. Deliverable Summary

| Task | Type | Files Changed/Created |
|------|------|-----------------------|
| 1 | Migration + Model | 2 files |
| 2 | Middleware | 3 files + route edit |
| 3 | API Controller | 1 file + route edit |
| 4 | API Controllers | 2 files + route edit |
| 5 | API Controllers | 2 files + route edit |
| 6 | API Controllers | 2 files + route edit |
| 7 | Web Controller + Blade View | 2 files + route edit |
| 8 | Sidebar Nav | 1 file |

**Total new commits on `main`:** 8 (one per task)
