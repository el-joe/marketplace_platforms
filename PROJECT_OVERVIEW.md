# Nawy Marketplace — Project Overview

A multi-vendor, multi-country marketplace. It covers products, classifieds, travel packages, marketers/influencers, delivery and finance, all on one Laravel backend.

## 1. Repository layout

| Folder | What it is | Stack |
|---|---|---|
| `backend/` | Core API + all web panels (admin, partner, marketer, etc.) | Laravel 13, PHP 8.3, Blade, Tailwind 4, jQuery DataTables, Vite |
| `frontend/` | Customer storefront (`nawyweb.com/{country}-{lang}/…`) | Next.js 16, React 19, React Query, next-intl |
| `partner_app/` | Seller (vendor) mobile app | Flutter |
| `delivery_app/` | Delivery agent mobile app | Flutter |
| `carrier_app/` | Shipping company supervisor app | Flutter |
| `travel_app/` | Travel agency mobile app | Flutter |
| `docs/`, `backend/docs/` | Client guides, plans, QA checklists | Markdown / DOCX |
| `postman/` | API collections | — |

**Runtime (PM2, `ecosystem.config.cjs`):** `mp-frontend` (Next on :3000), `mp-reverb` (WebSockets on :8080), and `mp-queue` (Laravel queue worker).

## 2. Panels (one backend, separated by subdomain)

| Subdomain | Users | Auth guard | Main jobs |
|---|---|---|---|
| `admin.` | Platform staff (roles: super/operations/finance/marketing/vendor-relations) | `admin` | Everything: catalog, vendors, orders, finance, campaigns, content, settings |
| `portal.` | New sellers | — | Vendor sign-up, onboarding, contract signing |
| `partner.` | Vendors (sellers) | `vendor` | Listings, sub-orders, inventory, ads, coupons, payouts |
| `marketer.` | Affiliates and influencers | marketer | Campaigns, promo codes/links, deals, earnings |
| `delivery.` | Delivery agents | `delivery_agents` | Accept → pick up → deliver assignments, GPS, earnings |
| `carrier.` | Shipping company supervisors | `shipping_company_supervisors` | Manage agents, assign shipments, SLA |
| `travel-agency.` | Travel agencies + team | `travel_agency` | Packages, bookings, inquiries |
| `nawyweb.com` | Customers | `customer` (JWT) | Storefront: shop, cart, checkout, classifieds, travel, profile |

Routes live in `backend/routes/{admin,partner,marketer,delivery,carrier,travel,portal}.php`; the APIs are in `routes/api_*.php`.

## 3. Business modules

- **Catalog**: products and variants, nested categories (with commission %, attributes and shipping methods), brands, highlights, bestsellers, vendor listings (one product sold by many vendors).
- **Vendors**: applications → review → approve; documents, certifications, change requests, contracts by country/category, subscriptions and plans.
- **Orders**: master order → one sub-order per vendor → items. Covers returns, disputes, warranty plans and claims, and reviews.
- **Fulfillment and shipping**: FBM (vendor ships) or FBN (platform warehouse). Also warehouses, transfers, inbound and storage fees, shipping zones/methods/rates/weight slabs, international rates, shipping subsidies, packaging.
- **Delivery**: carriers, agents, assignments, COD settlements, agent payouts, live map.
- **Marketing**: coupons (max 1 platform + 1 vendor per order), flash sales, banners, ad campaigns and paid ad slots, gift cards, vouchers, cart card offers, newsletter, push notifications.
- **Marketers**: affiliate campaigns (percentage / per-conversion / per-click; last-, first-click or linear attribution), influencer deals, marketer jobs, listings and ad packages.
- **Classifieds**: real estate, cars and services listings. Each listing needs a signed digital contract and admin approval.
- **Travel**: agencies create packages → admin approves → customers inquire or book.
- **Finance**: vendor payouts, wallets and withdrawals, ledger, transactions, refunds, financial reports, multi-currency with exchange rates.
- **Content**: page builder, CMS/static pages, blog, help center, ad-support articles, FAQs, footer.
- **Platform**: countries/cities/currencies, roles and permissions (Spatie), activity log, AI dashboard, analytics, live streams.

## 4. Core workflows

### Vendor onboarding
```
portal sign-up → application submitted → admin review (assign / approve / reject)
→ contract signed → vendor active in partner panel → creates listings
```

### Order lifecycle
```
Cart (15-min stock lock, guest cart token merged on login)
→ Checkout: subtotal + shipping + subsidy − coupons + COD fee + tax + warranty (+ gateway fee)
→ Payment: card | wallet | COD | BNPL (Tabby/Tamara) | gift card
→ Split into sub-orders per vendor
```
- **Sub-order:** `pending → processing → ready_for_pickup → shipped → out_for_delivery → delivered → completed`
- **Master order:** `pending → confirmed → partially_shipped → shipped → delivered → completed` (can branch to `cancelled / partially_refunded / refunded`)
- **Delivery:** the agent accepts → picks up (from the vendor or an FBN warehouse) → delivers. The customer reads a 4-digit OTP aloud and the agent checks it visually.
- **Auto-complete:** a daily job marks a sub-order `completed` after its return window ends. That unlocks the vendor payout and approves the marketer's conversion.

### Money flow
```
Customer pays → platform holds funds
→ sub-order completed (+ COD cash remitted by agent, if COD)
→ payout calculated (price − category commission − fees)
→ admin approves → processes payout → ledger entry
```
Marketer commissions and delivery agent earnings go through their own payout queues. Amounts are never summed across currencies.

### Marketer campaign
```
Vendor/marketer creates campaign (product | classified | travel) → admin approves
→ marketer shares link/code → click & order tracked (attribution model)
→ conversion: placed → approved (after return window) → paid | reversed
```
A budget guard pauses the campaign when its budget runs out.

### Classified listing
```
Customer/vendor creates listing → signs contract → admin approve/reject → live on storefront
```

### Travel package
```
Agency creates package → submits → admin approves → live
→ customer books/inquires → agency: pending_documents → confirmed → completed
```

## 5. Dev notes

- **Never edit or delete existing migrations.** This is a live database, so only add new ones, and never run `schema:dump --prune`.
- After CSS/JS changes in `backend/`, run `npm run build` (the built assets aren't committed).
- Admin tables: wrap them in `.table-responsive`. DataTables go through `window.initDataTable()` or `<x-table.datatable>`.
- Format PHP with `vendor/bin/pint --dirty`. Run tests with `php artisan test --compact` (PHPUnit) and Playwright e2e in `backend/tests/{admin,vendor,…}`.
- Detailed per-feature docs are in the admin panel under **Documentation** (`/docs`), with source in `backend/resources/views/admin/docs/` and `lang/*/docs/`.
