# Software Requirements Specification (SRS)
# Nawy Marketplace — Multi-Vendor, Multi-Country Commerce Platform

| | |
|---|---|
| Document | Software Requirements Specification |
| Product | Nawy Marketplace (backend panels, storefront, mobile apps) |
| Version | 1.0 |
| Date | 2026-11-14 |
| Basis | Written from the code on `main` (commit `c0fe74c1`): routes, services, models, enums, scheduler and in-app docs |
| Status | Describes the system **as built**. Section 14 lists the gaps and differences found while writing it. |

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Overall Description](#2-overall-description)
3. [User Classes and Actors](#3-user-classes-and-actors)
4. [Panels and Pages](#4-panels-and-pages)
   - 4.1 [Admin Panel](#41-admin-panel-admin)
   - 4.2 [Seller Portal (onboarding)](#42-seller-portal-portal)
   - 4.3 [Partner Panel (vendors)](#43-partner-panel-vendors-partner)
   - 4.4 [Marketer Panel](#44-marketer-panel-marketer)
   - 4.5 [Travel Agency Panel](#45-travel-agency-panel-travel-agency)
   - 4.6 [Delivery Agent Panel](#46-delivery-agent-panel-delivery)
   - 4.7 [Carrier Panel](#47-carrier-shipping-company-panel-carrier)
   - 4.8 [Customer Storefront](#48-customer-storefront-nextjs)
   - 4.9 [Mobile Apps](#49-mobile-apps-flutter)
5. [Catalog and Listings](#5-catalog-and-listings)
6. [Cart, Checkout, Payments](#6-cart-checkout-and-payments)
7. [Order Lifecycle, Returns, Disputes, Warranty](#7-order-lifecycle-returns-disputes-warranty)
8. [Fulfillment, Shipping, Delivery](#8-fulfillment-shipping-and-delivery)
9. [Finance](#9-finance)
10. [Platform Marketing and Advertising](#10-platform-marketing-and-advertising)
11. [Marketers and Marketer Campaigns](#11-marketers-and-marketer-campaigns)
12. [Classifieds, Travel](#12-classifieds-and-travel)
13. [Contracts and Documents](#13-contracts-and-documents)
14. [Live Streams and Scheduled Audio/Video Channels](#14-live-streams-and-scheduled-audiovideo-channels)
15. [Content, Support, Notifications](#15-content-support-and-notifications)
16. [Roles and Permissions](#16-roles-and-permissions)
17. [Scheduled Jobs](#17-scheduled-jobs)
18. [Non-Functional Requirements](#18-non-functional-requirements)
19. [Known Gaps and Differences](#19-known-gaps-and-differences)
20. [Appendix: Configurable Settings](#20-appendix-configurable-settings)

---

## 1. Introduction

### 1.1 Purpose
This document specifies what the Nawy Marketplace does: every panel and its pages, the lifecycle of every main business object (orders, listings, campaigns, contracts, bookings, payouts and more), and the rules that govern them. Product owners, QA, new developers and client stakeholders can use it as the reference for how the system behaves.

### 1.2 Scope
Nawy Marketplace is one platform that covers:

- **Product commerce**: many vendors sell the same catalog product, with FBM/FBN fulfillment.
- **Classifieds (Open Market)**: real estate, cars and services ads, with contracts.
- **Travel**: agencies publish trips, and customers inquire or book.
- **Marketers**: influencers, affiliates and brokers promote products, classifieds and trips for commission.
- **Logistics**: delivery agents, carriers, COD settlement.
- **Finance**: payouts, wallets, double-entry ledger, multi-currency.
- **Content and media**: page builder, blog, help center, live streams and scheduled audio/video channels.

### 1.3 Definitions

| Term | Meaning |
|---|---|
| Vendor / Seller / Partner | A business that sells on the platform (`product_vendor` or `classified_vendor`). |
| Product | A catalog item (with variants), shared by all sellers. |
| Vendor Listing | One vendor's offer (price, stock, fulfillment) for one product variant in one country. |
| Admin (Platform) Listing | An offer sold by the platform itself ("Nawy Listings"). |
| Marketer Listing | A listing on a marketer's own page. It is either created from a campaign invitation, independent (bound to a source listing), or an influencer's own FBN stock. |
| Marketer | An influencer, affiliate and/or broker account (a marketer can hold several types). |
| Campaign | A `MarketerCampaign` in which marketers promote one item (product, classified or trip). |
| Invitation | A marketer's seat on a campaign. It carries a unique referral code, link (`/r/{code}`) and QR. |
| Conversion | A sale, booking or lead credited to a marketer's invitation. |
| Peer promotion | An influencer promotes another influencer's own listing (marketer ↔ marketer). |
| FBM / FBN / FBP | Fulfilled by Merchant / by Nawy (platform warehouse) / by Partner. |
| COD | Cash on Delivery. |
| Master order / Sub-order | One checkout creates one order, split into one sub-order per seller. |
| Base units | Money is stored per currency, never converted or summed across currencies. |

### 1.4 References (in the repository)
- `PROJECT_OVERVIEW.md`: architecture summary.
- `docs/marketer-campaign-flow.md`: campaign audit (Oct 2026). Some gaps it lists are now fixed; see §19.
- `docs/contracts-documents-flow-client-guide-ar.md`: contracts guide (Arabic).
- `docs/LIVE_STREAMS_VPS_NGINX.md` and `docs/LIVE_STREAMS_VPS_APACHE.md`: live-stream deployment.
- `client_requirements.md`: requirements 1–10 (portal permissions, analytics, contracts, classified sections).
- In-app documentation: Admin → Documentation (`/docs`), with sources in `backend/resources/views/admin/docs`.

---

## 2. Overall Description

### 2.1 Architecture

| Component | Folder | Stack |
|---|---|---|
| Core API + all web panels | `backend/` | Laravel 13, PHP 8.3, Blade, Tailwind 4, jQuery DataTables, Vite |
| Customer storefront | `frontend/` | Next.js 16, React 19, React Query, next-intl. URLs look like `nawyweb.com/{country}-{lang}/…` |
| Seller app | `partner_app/` | Flutter |
| Delivery agent app | `delivery_app/` | Flutter |
| Carrier supervisor app | `carrier_app/` | Flutter |
| Travel agency app | `travel_app/` | Flutter |
| Real-time | Laravel Reverb (WebSockets, port 8080) | Notifications, chat, live-stream signalling |
| Queue worker | `mp-queue` (PM2) | Mail, notifications, WhatsApp, timed jobs |
| Media relay | coturn (TURN/STUN) | WebRTC fallback for live streams |

All panels run in **one Laravel application**, separated by subdomain. Route files are `routes/{admin,partner,marketer,delivery,carrier,travel,portal}.php`, and the APIs are in `routes/api_*.php` (customer v1, vendor, partner, marketer, delivery, carrier, travel agency, public).

### 2.2 Subdomains and guards

| Subdomain | Users | Guard |
|---|---|---|
| `admin.` | Platform staff | `admin` |
| `portal.` | Prospective sellers, travel agencies and marketers (landing pages + sign-up) | guest |
| `partner.` | Vendors and their team | `vendor` |
| `marketer.` | Influencers, affiliates, brokers | `marketer` |
| `travel-agency.` | Travel agencies and their team | `travel_agency` |
| `delivery.` | Delivery agents | `delivery_agents` |
| `carrier.` | Shipping company supervisors | `shipping_company_supervisors` |
| root domain | Customers (storefront) | `customer` (JWT) |

### 2.3 Global constraints
- **Multi-country and multi-currency.** Supported currencies include SAR, AED, OMR, KWD, QAR, BHD, EGP and JOD. Amounts are **never summed across currencies**: every report and payout groups by currency first.
- **Rounding.** `FLOOR()` for percentage calculations and `ROUND()` for flat rates in legacy code. Marketer and campaign money is decimal(19,4) and is rounded half-up to 2 decimals when credited.
- **Bilingual.** Arabic and English, with RTL support. Most content entities carry `_ar` and `_en` fields.
- **Live database.** Migrations are append-only: never edited, rolled back or pruned.
- **Append-only records.** Inventory movements, wallet transactions, page/block revisions, the activity log and contract signatures are never updated or deleted.

---

## 3. User Classes and Actors

| Actor | Description | Main panels |
|---|---|---|
| Super Admin / Admin staff | Roles such as super, operations, finance, marketing and vendor-relations, with Spatie permissions | Admin |
| Account manager admin | An admin limited to the vendors assigned to them, with financial data hidden | Admin |
| Vendor owner / Vendor team member | Sellers and their staff, with vendor-scoped roles | Portal → Partner, Partner app |
| Classified vendor | A seller of classified ads (property, cars, services) | Partner |
| Travel agency owner / member | Agency staff, with agency-scoped roles; the owner bypasses permission checks | Travel Agency, Travel app |
| Influencer | A marketer paid **one fixed amount per campaign**. Can own FBN stock and promote peers. | Marketer |
| Affiliate | A marketer paid a **percentage of each sale** | Marketer |
| Broker | A marketer paid **fixed + percentage per sale**, and matched to customer special requests | Marketer |
| Customer | A shopper, buyer of classifieds, or traveller (guests can browse and use a cart) | Storefront |
| Delivery agent | Picks up and delivers, collects COD | Delivery, Delivery app |
| Carrier supervisor | Manages a shipping company's agents and shipments | Carrier, Carrier app |
| System (scheduler / queue) | Timed transitions, approvals, settlements | — |

---

## 4. Panels and Pages

Each page entry follows the pattern **Page → what it shows / what the user can do**. Badges in the admin sidebar show pending counts: pending orders, disputes, returns, vendors, campaigns, marketers, classifieds, travel packages, product requests, certifications, change requests, tickets and contract signatures.

### 4.1 Admin Panel (`admin.`)

Every menu item is gated by a permission (for example `orders.view`). Groups and pages:

#### 4.1.1 Overview
| Page | Description |
|---|---|
| **Dashboard** | Platform health: revenue chart, order counts, top sellers, pending work items. |
| **Analytics** | Revenue by period, top products, vendors and categories, customer stats, SLA metrics, flash-sale analytics, return rates, support metrics. |
| **AI Dashboard** | AI features: image enhancement and video generation jobs, AI credits. |

#### 4.1.2 Catalog
| Page | Description |
|---|---|
| **Products** | Create and edit products with variants, images, GTIN, attributes and per-country settings. Product status: `draft → active → discontinued / restricted`. |
| **Product Requests** | Requests from vendors or marketers to add a product that is missing from the catalog. Admin approves (creates the product) or rejects. Status: `pending → approved / rejected / cancelled`. |
| **Platform Listings (Nawy Listings)** | The platform's own offers (`AdminListing`): price, stock, status `active / paused / out_of_stock / archived`. |
| **Categories** | Nested category tree. Per category: sale commission %, attributes, allowed shipping methods, campaign sample quantities (platform, influencer, affiliate), `min_stock_for_campaign`, individual-contract switch, Marketers tab (affiliate defaults). |
| **Custom Pages** | Category landing / custom pages. |
| **Brands** | Brand CRUD with logos. |
| **Attributes** | Attributes and values used for variants and filters. |
| **Warranty Plans** | Extended warranty plans scoped to parent categories, sold at checkout. |

#### 4.1.3 Inventory & Fulfillment
| Page | Description |
|---|---|
| **Flash Sales** | Create an event, set its window, invite vendors and marketers, review submitted prices, monitor live, see analytics and price history. |
| **Warehouses** | Platform FBN warehouses and vendor warehouses: inventory, adjustments, free storage days, vendor storage limits. |
| **Inventory Transfers** | Move stock between warehouses: `draft → in_transit → received / cancelled`. |
| **Shipping Surcharges** | Outbound surcharges on FBN shipments, per warehouse. |
| **FBN Inbound** | Vendor inbound shipments to platform warehouses: `draft → submitted → approved → shipped → received / rejected`. |
| **FBN Marketplace** | Marketplace shipping rules for FBN. |
| **FBN Storage Fees** | Monthly storage fee invoices, built from daily overage fees. |
| **Packaging Catalog** | Branded packaging supplies the platform sells to vendors. |
| **Packaging Orders** | Vendor orders for supplies: `pending → approved → shipped → delivered / rejected`. |

#### 4.1.4 Orders & Disputes
| Page | Description |
|---|---|
| **Orders** | All orders, with filters. Admin can view or change status, assign shipping, mark shipped or delivered, force-cancel, refund and flag fraud. Admin can also move a sub-order between states. |
| **Disputes** | Customer disputes: assign, reply, mediate, resolve (`favor_customer / favor_seller / split / no_action`). |
| **Returns** | Return requests: approve or reject, arrange pickup, inspect, refund. |
| **Warranty Claims** | Claims (repair / replace / refund), with a message thread between customer, vendor and admin. |

#### 4.1.5 Users & Vendors
| Page | Description |
|---|---|
| **Customers** | Suspend, ban or reactivate; adjust loyalty points; export data; send notifications; regenerate QR; revoke devices. |
| **Wishlists** | Public wishlist groups. |
| **Notifications** | Notification management and broadcast. |
| **Vendors** | Active vendors: suspend, blacklist, assign account manager, lock/unlock profile sections, issue strikes, put on hold, see contracts and signatures. |
| **Vendor Listings** | Every vendor listing on the platform. |
| **Applications** | Vendor application queue: start review, assign to me, approve or reject. |
| **Vendor Change Requests** | Requested changes to locked profile sections, such as bank accounts. |
| **Product Certifications** | Certificates a vendor must provide to sell certain products; approve or reject. |
| **Acquisition Commissions** | Commission paid to admins who bring in vendors: a rate inside a monthly min/max sales band, valid for a date range. Processed monthly. |
| **My Acquisition Commissions** | The logged-in admin's own acquisition earnings. |
| **Admins** | Admin CRUD, impersonation, sessions, password reset. |
| **Roles** | Admin roles and permissions. |

#### 4.1.6 Marketers & Campaigns
| Page | Description |
|---|---|
| **Marketers** | All marketers. Approve, reject, suspend or activate. Edit the profile and per-category commission rules (fixed, percentage, fee). Sync job types. Manage the marketer's **onboarding contract** (upload a version, toggle required, view acceptances, download), **collaboration contracts** signed by sellers, and **exclusive contracts**. |
| **Marketer Campaigns** | All campaigns. Create a platform campaign on a Nawy listing, view a campaign, approve or reject, approve a marketer-originated request, invite more marketers, resend WhatsApp invitations, update sample status (dispatch and so on). |
| **Marketer Campaigns Revenue (Financials)** | Campaign fees, commission fees and the platform's profit from campaigns. |
| **Marketer Settings** | The influencer fixed-amount grid (country × vertical × category), influencer overrides, the commission-fee grid, and general settings (attribution window, referral grace days, payout clearing days, classified approval days, peer minimum stock). |
| **Marketer Jobs** | Job types (influencer, affiliate, broker and others) and the category types each one serves; activate or deactivate. |
| **Ad Packages** | Paid packages marketers subscribe to (price, VAT, duration, features, target type). Admin approves or rejects subscriptions paid by bank transfer. |
| *(from a marketer's page)* **Marketer Listings** | Review queue for marketers' own listings: approve or reject. |

#### 4.1.7 Marketing & Ads
| Page | Description |
|---|---|
| **Marketing Fees** | Campaign fee configuration: fixed part per country (amount and/or %), variable part per country × category × fulfillment type (FBM/FBN/FBP), and per country × vertical for classified and travel. Includes a calculator and a report export. |
| **Banners** | Placement-based banners with schedule, audience (gender, device) and link type. Supports duplicate and bulk actions. Status: `active / inactive / scheduled / expired`. |
| **Cart Card Offers** | Offers shown in the cart for paying with a specific card. |
| **Ad Campaigns** | Vendors' self-serve CPC/CPM keyword and sponsored-product ads. Approve, reject, pause, resume; review fraud alerts. |
| **Nawi Ads (Ad Slots)** | Named ad positions: placement, dimensions, pricing model (fixed daily, weekly or monthly, CPM, CPC), maximum concurrent bookings. |
| **Paid Ad Bookings** | Reservations of slots by vendors **and marketers**: review the creative, then approve or reject. |
| **Coupons** | Platform, vendor, category and product coupons (percentage, fixed amount, free shipping, BOGO), with customer eligibility rules. |
| **Coupon Participation Invitations** | Admin opens a paid invitation for vendors and marketers to join a platform coupon. Participants offer a fee of at least the minimum; admin approves (the fee is charged from the wallet) or confirms a bank transfer; rejecting refunds the fee. |
| **Vouchers** | Admin vouchers. |
| **Gift Cards** | Create (value, currency, expiry), cancel, extend, adjust balance. |
| **Newsletter** | Subscribers and campaigns. |

#### 4.1.8 Classifieds & Open Market
| Page | Description |
|---|---|
| **Categories** | Classified categories. These are dynamic and can have dedicated website sections. Each one links seller and customer contracts per country. |
| **Listings** | Review queue: approve or reject, verify attachments. |

#### 4.1.9 Contracts
| Page | Description |
|---|---|
| **Contract Templates** | Templates of every kind (§13): editor or uploaded-file mode, scope, country, versions, publish, assignments. |
| **Customer Contracts** | Customer purchase/rental contract acceptances. |
| **Contract Signatures** | Every seller signature, filtered by scope, status and vendor; signed copies can be downloaded. |

#### 4.1.10 Travel
| Page | Description |
|---|---|
| **Agencies** | Approve, suspend or reactivate agencies; handle change requests for locked sections. |
| **Packages** | Review queue (approve / reject / expire), contract download. |
| **Bookings** | All bookings on the platform. |
| **Countries / Cities** | Destination catalog. |
| **Inclusions** | Standard inclusions (meals, transfers…). |
| **Travel Categories** | Trip categories. |
| **Travel Inquiries** | Public inquiries from the storefront. |
| **Bookable Units** | Rooms, seats and other units agencies sell. |

#### 4.1.11 Delivery & Logistics
| Page | Description |
|---|---|
| **Delivery Agents** | Create agents, verify documents, assign zones. |
| **Delivery Zones** | Zone definitions and a live map of agents. |
| **Delivery Assignments** | Auto-assign or manual assign, with a live map. |
| **Carrier Claims** | Claims against carriers for lost or damaged shipments: `submitted → under_review → approved / rejected → compensated`. |
| **Carrier Scorecard** | Performance score and trend per carrier. |
| **Shipping Companies** | Carrier companies, supervisors, fallback rules. |

#### 4.1.12 Finance & Payouts
| Page | Description |
|---|---|
| **Payouts** | Vendor payout batches: approve, process, hold, recalculate. |
| **Delivery Payouts** | Agent payouts: generate, approve, process. |
| **Transactions** | Raw payment transactions and refunds. |
| **Ledger** | Double-entry ledger with transaction groups. |
| **COD Settlements** | Cash remitted by agents. Marking one settled unlocks the vendor payout. |
| **Financial Reports** | Revenue reports with export. |
| **Warranty Purchases** | Extended warranty sales. |
| **Wallets** | All wallets: adjust, freeze, approve withdrawals. |
| **Subscriptions / Plans / Invoices** | Vendor subscription plans (monthly or annual, priced per country), subscribe or cancel a vendor, invoices. |

#### 4.1.13 Content
Pages (page builder), App Contexts, Reviews, Blog Categories, Blog, Knowledge Hub Collections and Articles, FAQs, Static Pages, Portal Content, Portal Analytics, Footer Settings, **Live Streams**. See §14 and §15.

#### 4.1.14 Platform Settings (System)
Countries (launch or deactivate; per-country payment methods, shipping and VAT), Cities (CSV import), Currencies (rates), Shipping Zones, Shipping Methods, Shipping Settings, Document Types (vendor documents required per country), Payment Gateways (credentials, test connection, webhook logs), Weight Slabs, Shipping Subsidies, Vendor Zone Alerts, and the Activity Log (immutable audit trail).

#### 4.1.15 Support & Help Center
Support Tickets (reply, assign, status and priority), Help Center Categories, Help Center Articles.

#### 4.1.16 Documentation
In-app guides for each panel (Admin, Partner, Travel, Delivery, Carrier) and each feature (Order Lifecycle, Shipping, Warehouses, Payments, Finance, Subsidy, Page Builder, Banners, Ad Campaigns, Vendor Campaigns, Flash Sales, Packaging, Warranties, Classifieds, Travel, Roles, Content and System pages).

---

### 4.2 Seller Portal (`portal.`)

| Page | Description |
|---|---|
| **Join** (`/join`) | Choose what to join: seller, classified seller (Open Souq), travel agency or marketer. |
| **Landing pages** | `/vendors`, `/how-it-works`, `/fulfillment`, `/smart-tools`, `/faq`, `/marketers` and `/marketers/{type}`, `/companies/open-souq`, `/companies/travel-agency`. |
| **Advertise pages** | `/{country}/advertise/sellers`, `brands`, `advertisers`, `product`, `display`, `request`. The request form creates an *Advertise Inquiry*. |
| **Vendor registration** (`/vendors/register`) | Steps: 1 account, 2 store (with slug check), 3 address and country/city; then documents (required per country by Document Types), then the **Seller Agreement** signature, then complete. Each step is tracked for funnel analytics. |
| **Travel agency registration** | A 2-step registration, then complete. |
| **Legal** | `/legal/seller-agreement`: the public terms; the agreement file can be viewed per language. |
| **Knowledge Hub / Help Center** | Country-prefixed ad-support and help-center articles. |
| **Static pages** | `/pages/{slug}`, country-specific with a global fallback. |

The selected country persists across portal pages (client requirement 10). Admin sees visitors and the registration funnel under **Portal Analytics**.

---

### 4.3 Partner Panel (vendors) (`partner.`)

The sidebar is grouped as follows. Some items only appear for `product_vendor` or for `classified_vendor` (middleware `vendor.type`). Each action is gated by a vendor permission (`vendor.can:*`).

| Group | Page | Description |
|---|---|---|
| Main | **Home (Dashboard)** | Sales KPIs, pending work, COD notices. |
| | **Notifications** | In-app notifications. |
| Sales | **My Orders** | The vendor's sub-orders: confirm, pack, ready for pickup, cancel (this hurts the seller score), print. |
| | **Returns** | Return requests on the vendor's items. |
| | **Warranty Claims** | Claims on the vendor's own listings only (claims on admin listings return 403). |
| | **Claims** | Carrier or shipping claims. |
| | **Disputes** | Respond to customer disputes. |
| Catalog | **Listings** | Create and edit listings (price, stock, condition, fulfillment model, promo badges). Status: `draft → pending_review → active ⇄ paused`, `out_of_stock`, `rejected`, `archived`. Contract and certification gates apply (§13). |
| | **Product Requests** | Ask the admin to add a product. |
| | **Size Guides** | The vendor's size charts. |
| | **Inventory** | Stock levels and adjustments. |
| | **Change Requests** | Requests to change locked profile sections. |
| | **AI Credits** | AI image and video credits. |
| | **Weight Calculator** | Volumetric weight tool. |
| Fulfillment & Storage | **Fulfillment** | FBN inbound requests and tracking. |
| | **Warehouses / Transfers** | The vendor's warehouses and stock transfers. |
| | **City Shipping Surcharges** | Per-city surcharges. |
| | **Exceptional Zones** | Opt in to subsidized free delivery for remote zones. |
| | **Packaging Supplies** | Order branded packaging (a cart with a delivery fee that is never subsidized). |
| Vendor Campaigns | **My Campaigns (Marketer Campaigns)** | Create a campaign from a listing or a classified ad with the wizard, view it, edit while pending, cancel while pending, **cancel an active one**, pause or resume, invite marketers, enter sample custom attributes, **approve a marketer's request**, search marketers. See §11. |
| Marketing | **Flash Sales** | Accept an invitation and submit discounted prices. |
| | **Coupons** | The vendor's own coupons. |
| | **Coupon Participation Invitations** | Apply (with a fee) to join platform coupons. |
| | **Ads** | Self-serve CPC/CPM ad campaigns. |
| Contracts | **Legal Documents** (`/legal`) | The agreement in force, signature info, download of the signed copy, signature history, and a banner when a scheduled new version is coming. |
| | **Pending Contracts** | Category or product contracts that must be signed before publishing. |
| | **Contract History** | Every signed contract (text or file). |
| Advertising | **Nawi Ads** | Browse ad slots. |
| | **Ad Bookings** | Book slots for dates, upload the creative, pay. |
| Open Market | **My Classifieds** | Create classified ads (sign the contract first), mark as sold (with the campaign sale credit, see §12.1), pause. |
| Other | **Performance** | Seller score, SLA, strikes. |
| | **Support** | Tickets. |
| Finance | **Payouts** | Payout statements (weekly, biweekly or monthly schedule). |
| | **Transactions**, **Sales Report** | Financial detail. |
| | **Wallet** | Balance; campaign fees are charged here first. |
| | **Subscription** | The vendor's plan. |
| Developer | **API & Tokens** | API tokens for integrations. |
| Settings | **My Profile**, **Shipping Companies**, **Payment Methods**, **Team**, **Roles**, **Bank Accounts**, **Documents** | Profile (locked sections go through change requests), team members and custom roles, bank accounts (verified), onboarding documents (`pending → approved / rejected`). |

**Click-wrap gate:** when a new Seller Agreement version needs explicit consent, every partner page redirects to the consent page until the vendor signs.

---

### 4.4 Marketer Panel (`marketer.`)

| Area | Page | Description |
|---|---|---|
| Auth | **Login / Register / Forgot password** | Self sign-up. The account starts `pending` until admin approval. |
| | **Onboarding** | Choose job type(s) (influencer, affiliate, broker), then fill the profile. |
| | **Contract** (`/contract`) | Read and accept the marketer ↔ platform **onboarding contract**. Required before accepting any campaign. |
| Home | **Dashboard** | Earnings, active campaigns, conversions. |
| | **Notifications** | List, unread, mark read. |
| | **Profile** | Public profile data, social accounts, **ad price** (an influencer's price per ad). |
| Campaigns | **Invitations** | Pending campaign invitations: accept (with an optional note) or reject (with a reason). Invitations can also be answered from a **signed WhatsApp link** without logging in. |
| | **Active Campaigns** | Campaigns the marketer is on: referral link, code, QR, stats. |
| | **Finished Campaigns** | Done, cancelled or rejected campaigns with totals. |
| | **Campaign detail** | One invitation with its conversions. |
| | **Request a campaign** | Propose to promote a vendor or platform listing (owner approval needed), or **promote another influencer's listing** (peer promotion, live at once). |
| | **Peer Products** | Other influencers' FBN listings available for peer promotion. |
| | **Promoted by Others** | The marketer's own listings that other influencers are promoting. |
| | **Samples** | Product samples: submit a delivery address, confirm receipt. |
| Selling | **My Listings** | The marketer's own listings: create (search the product), edit, adjust stock, change price, promo badges, resubmit after rejection, toggle status, delete. Status: `draft → pending_review → active ⇄ paused / out_of_stock / rejected / archived`. |
| | **Inventory** | All, low-stock and out-of-stock views. |
| | **Orders** | Orders that contain the marketer's listings. |
| | **Product Requests** | Ask the admin to add a product to the catalog. |
| Classifieds | **Classified Listings** | Brokers' and marketers' classified ads (CRUD, toggle). |
| | **Classified Inquiries** | Buyer inquiries: view, close. |
| | **Wanted Listings** | "Wanted" ads. |
| | **Exclusive Contracts** | Exclusivity granted by the admin on a classified category or listing; view and download. |
| | **Special Requests** | Customer special requests routed to matching **brokers**: view, start (claim). |
| Communication | **Conversations** | Chat with customers (real time, unread count). |
| | **Support** | Tickets. |
| Promotion | **Promote** | Book paid ad slots: slot detail, availability calendar, quote, destinations, pay from wallet; then manage bookings (upload creative, submit, pay, cancel, stats). |
| | **Ad Packages** | Subscribe to marketer ad packages (wallet, or bank transfer with proof). |
| | **Flash Sales** | Accept or decline flash-sale invitations (bonus commission). |
| | **Coupon Participation** | Apply to join platform coupons. |
| Contracts | **Contracts** | Onboarding contract versions, plus **collaboration contracts** that sellers signed for this marketer (view and download the file). |
| Finance | **Commissions** | Conversions with gross, fee and net amounts, and their status. |
| | **Wallet** | Pending balance, spendable balance, withdrawal requests. |
| | **Reports** | Performance reports. |

---

### 4.5 Travel Agency Panel (`travel-agency.`)

| Page | Description |
|---|---|
| **Dashboard** | Bookings, revenue, inquiries. |
| **Packages** | Create trips (destination, dates, price, seats, inclusions), then submit for review. Status: `draft → pending_review → active → sold_out / expired`. |
| **Bookable Units** | Rooms, seats and other units. |
| **Bookings** | Manage bookings: `pending_documents → confirmed → completed`, or `cancelled`. |
| **Inquiries** | Customer inquiries: `new → contacted → converted / closed`. |
| **Campaigns** | Marketer campaigns on the agency's trips: create (also straight from a trip), quote, view, pause, resume, cancel, export. See §11. |
| **Finance** | Wallet (campaign fees are charged here), Payouts, Revenue, Sales Report. |
| **Reports** | Bookings, packages and revenue reports. |
| **Performance** | Agency performance. |
| **Team / Roles** | Members and agency-scoped roles. |
| **Bank Accounts** | Verified bank accounts. |
| **Profile** | Locked sections change through admin change requests. |
| **Support** | Tickets. |

---

### 4.6 Delivery Agent Panel (`delivery.`)

| Page | Description |
|---|---|
| **Dashboard** | Today's assignments and earnings; available/unavailable toggle. |
| **Assignments** | `assigned → accepted → picked_up → delivered / failed`. A 4-digit OTP is **shown to the agent**; the customer reads it aloud and the agent matches it by eye (the agent does not type it). |
| **Earnings** | Earnings per assignment, weekly and monthly summaries. |
| **Wallet** | Balance and withdrawal requests. |
| **COD Settlements** | Cash collected and settlement history (`pending → settled / disputed`). |
| **Profile** | Documents, vehicle, live GPS location. |

### 4.7 Carrier (Shipping Company) Panel (`carrier.`)

| Page | Description |
|---|---|
| **Dashboard** | Active deliveries, agent count, SLA. |
| **Agents** | Create, suspend or activate the company's agents (no cap). |
| **Supervisors** | Only an owner-level supervisor can create other supervisors. |
| **Assignments / Unassigned** | Assign shipments to agents, reassign, track status. |
| **Reports** | Carrier performance reports. |

---

### 4.8 Customer Storefront (Next.js)

URLs are prefixed `/{country}-{lang}`.

| Page | Description |
|---|---|
| **Home & category pages** (`/[...categorySlug]`) | Built by the page builder (hero slider, ad images, category chips, brand rows, product grids and carousels, flash-sale countdown, vendor spotlight, HTML, banners). |
| **Product page** (`/products/[slug]`) | Buy box across vendor, platform and marketer listings; variants; warranty plans; reviews; size guide (`/size-guide/[id]`). |
| **Seller page** (`/seller/[seller_id]`) | A vendor's storefront. |
| **Marketers directory** (`/marketers`) | Flat and grouped lists of marketers with filters, gender and banners. |
| **Marketer page** (`/marketer/[slug]`) | The marketer's public profile, listings (each attributed directly) and contracts shown on the profile. |
| **Referral redirect** (`/r/[code]`) | Records the click (attribution) and redirects to the promoted item. |
| **Classifieds** (`/classified`, `/classified/[categorySlug]`, `/classified/find/[slug]`) | Dedicated sections per classified category, a map view (GeoJSON), inquiries (guest allowed, throttled at 5/min). A customer purchase contract is required where one is configured. Exclusive marketer badges are shown. |
| **Travel** (`/travel`, `/travel/[slug]`) | Browse, inquire about and book trips. |
| **Live Streams** (`/live-streams`, `/live-streams/[id]`) | Watch live WebRTC broadcasts and scheduled audio/video channels; comment and like. See §14. |
| **Gift Cards** (`/gift-cards`, `/gift-cards/[slug]`) | Buy gift cards. |
| **Static pages** (`/pages/[slug]`), **Nawy Options** | CMS pages. |
| **Cart / Checkout / Success** | §6. |
| **Wishlist** | Saved items and wishlist groups. |
| **Profile area** | Profile, Addresses, Orders (detail, cancel, invoice, summary), Returns, Disputes, Warranty Claims (create), My Warranties, My Bookings, **My Contracts** (customer purchase contracts), Chats (with marketers), Special Requests (create and track), Tickets, Notifications, Payments, Noon Credits (wallet), QR Code, Security Settings and Manage Devices. |

### 4.9 Mobile Apps (Flutter)

| App | Purpose | API |
|---|---|---|
| `partner_app` | Vendor operations (orders, listings, inventory, coupons, payouts) | `api_vendor.php`, `api_partner.php` |
| `delivery_app` | Agent assignments, OTP, GPS, earnings, COD | `api_delivery.php` |
| `carrier_app` | Supervisors: agents, assignments | `api_carrier.php` |
| `travel_app` | Agency packages, bookings, inquiries | `api_travel_agency.php` |
| Marketer API | Marketer features (invitations, campaigns, listings, exclusive contracts) | `api_marketer.php` |

The customer app uses `api_customer_v1.php`. Public settings and streams use `api_public.php`.

---

## 5. Catalog and Listings

### 5.1 Products
- **FR-CAT-1** A product has variants, attributes, images and per-country settings. Status: `draft → active → discontinued | restricted`.
- **FR-CAT-2** A vendor or marketer can only list an existing product. Missing products go through **Product Requests** (`pending → approved | rejected | cancelled`).
- **FR-CAT-3** Bestsellers are computed from `total_sold` by a rankings job. Product highlights pin products to featured spots.

### 5.2 Vendor Listing lifecycle
```
draft ──submit──► pending_review ──admin──► active ⇄ paused
                        │                     │
                        └──► rejected         ├─► out_of_stock (stock = 0, automatic)
                                              └─► archived
```
- **FR-LST-1** Before the first listing in a category or product that requires a contract, the vendor must sign it (ListingContractGate). Required product certifications must be approved (ListingCertificationGate).
- **FR-LST-2** Fulfillment model: `fbm`, `fbn` or `cross_dock`. An FBN listing is orderable only while `quantity_available > 0` in a platform warehouse.
- **FR-LST-3** `quantity_available` is a virtual column (on hand − reserved) and is never written directly. Every stock change writes an append-only `inventory_movement`.

### 5.3 Platform (Admin) listings
`active ⇄ paused`, `out_of_stock`, `archived`. These can be promoted through **platform-owned campaigns**; such campaigns carry no campaign fee, and commissions are paid from platform revenue.

### 5.4 Marketer listings
There are three kinds:
1. **Campaign listing**: created automatically when a marketer accepts an invitation. It copies the source price and carries the invitation's referral code.
2. **Independent listing**: created by the marketer. It must bind at creation time to an existing active vendor or admin listing for the same variant and country (the cheapest one, with vendor listings preferred). If none exists, creation is refused.
3. **Own FBN listing (influencer)**: the influencer's own stock in a platform warehouse (`source_listing_id = NULL`). The influencer is the seller of record, and other influencers can **peer-promote** it (§11.6).

Status: `draft → pending_review → active ⇄ paused | out_of_stock | rejected | archived`. Admin reviews these under Marketer Listings. Campaign listings are paused, archived or resumed together with their campaign.

---

## 6. Cart, Checkout and Payments

- **FR-CHK-1 Cart.** Adding to the cart soft-locks inventory for **15 minutes** (`cart_inventory_locks`, released every minute by a job). A guest cart uses the `X-Cart-Token` header and is merged into the customer's cart on login.
- **FR-CHK-2 Calculation** (per currency): subtotal + shipping (zone, method, volumetric weight, slabs, surcharges) − exceptional-zone subsidy − coupons (**at most 1 platform + 1 vendor coupon**) + COD fee + tax/VAT (country rules) + warranty plan (+ gateway fee). Orders at or above the free-shipping threshold ship free, with the platform absorbing the cost.
- **FR-CHK-3 Payment methods** are configured per country: card (gateways via a strategy pattern, for example Thawani, which takes amounts in baisa inside its gateway class only), wallet, COD, BNPL (Tabby / Tamara), gift card. Order payment status: `pending → authorized → captured | failed | refunded | partially_refunded`. Pending payments that never complete expire (every 5 minutes).
- **FR-CHK-4 Marketer contract gate.** If a cart line comes from a marketer whose contract is marked *required* and has a published version, the customer must accept that marketer's contract before checkout.
- **FR-CHK-5 Split.** Checkout creates one order, one sub-order per seller (vendor, platform or marketer seller) and N order items. All settlement happens at sub-order level.
- **FR-CHK-6 Attribution** is recorded per order item at checkout (§11.8).

---

## 7. Order Lifecycle, Returns, Disputes, Warranty

### 7.1 Sub-order lifecycle (OrderStateMachine)
```
placed → confirmed → processing → packed → shipped → out_for_delivery → delivered → completed
   └────────────── cancelled (customer before shipped, vendor with score penalty, admin at any stage)
delivered → returned → refunded
```
- Every transition writes `order_status_history` with the actor (customer, vendor, admin or system) and updates the items' `fulfillment_status`. Events `SubOrderShipped`, `SubOrderDelivered` and `SubOrderReturned` drive notifications.
- **Auto-complete** (daily at 02:00): a delivered sub-order becomes `completed` once its return window has passed. This unlocks the vendor payout and the marketer conversion approval.

### 7.2 Master order roll-up
The master order status is derived from its sub-orders: `placed → confirmed → partially_shipped → shipped → partially_delivered → delivered → completed`, or `cancelled / refunded / disputed`. For example, it is `completed` only when every sub-order is completed; it is `cancelled` when every sub-order is cancelled or refunded.

### 7.3 Cancellation
Cancelling releases inventory, starts a refund and **reverses the marketer conversion** (unless that conversion was already paid out; recovering a paid-out commission is manual).

### 7.4 Returns
`requested → approved | rejected → awaiting_pickup → in_transit → received → inspecting → completed | cancelled`. The inspection outcome decides the refund: good condition gets a full refund; damaged by the customer gets a partial or no refund; damaged in transit gets a full refund and the carrier is investigated. A restock decision is recorded per item.

### 7.5 Refunds
`pending → approved → processing → completed | failed | rejected`. A card refund goes back to the card (5–10 days), a wallet refund is instant, and a COD refund goes to the customer's wallet.

### 7.6 Disputes
`open → seller_responded → under_review → escalated → resolved → closed`. The resolution is one of favor_customer, favor_seller, split or no_action.

### 7.7 Warranty
- **Standard warranty** is the vendor's and is claimed by the customer.
- **Extended plan** is bought at checkout and covered by the platform; the payment goes to the platform. Expiry runs daily.
- A claim is resolved by repair, replace or refund, with a three-party message thread.

### 7.8 Reviews
Status: `pending → published | flagged | auto_flagged | hidden | rejected`. Vendors can reply; admin moderates replies.

---

## 8. Fulfillment, Shipping and Delivery

- **Shipping model:** Zones (groups of cities) → Methods per zone → Rates (base + per kg) → Weight slabs → FBN surcharges. Billable weight is the higher of actual and volumetric weight. Estimated delivery dates skip Fridays and public holidays.
- **Exceptional zones / subsidy:** a vendor opts in per zone. The customer sees free delivery; the vendor's share is deducted from the payout and the platform's share is a real cost in the P&L.
- **FBN:** inbound request → admin approves → vendor ships → warehouse receives. The free-storage clock starts on receipt (default 30 days). After that, a daily overage fee is computed at 01:00 and invoiced monthly, deducted from the payout. Overstored unpaid products are flagged daily.
- **Delivery assignment:** `assigned → accepted → picked_up → delivered | failed`. Pickup is from the vendor (FBM) or the FBN warehouse. SLA breaches are checked every 15 minutes.
- **Shipments:** `label_created → picked_up → in_transit → out_for_delivery → delivered | failed | returned`.
- **COD:** the agent collects the cash. A settlement is generated daily at 23:30; when admin marks it settled, `cod_remittance_confirmed = true` and the vendor payout for that sub-order unlocks. Until then the vendor sees an amber notice.
- **Carrier claims and scorecard** are described in §4.1.11.

---

## 9. Finance

| Flow | Steps |
|---|---|
| **Vendor payout** | Eligible sub-orders (completed, or delivered past the return window; COD only once remitted) → statement generated on the vendor's schedule (weekly on Monday 02:00, biweekly on the 1st and 15th, monthly on the 1st) → admin **approve → process** (bank transfer), or hold or recalculate. Status: `pending → approved → processing → completed | failed | on_hold`. Net = price − category commission − fees − subsidy share − FBN fees − pending **campaign fees** − sample commissions. |
| **Marketer commission** | Conversion approved → **pending balance** → after `marketer_payout_clearing_days` → **spendable balance** → withdrawal request (`pending → approved → processed | rejected`). See §11.11. |
| **Marketer as seller** | Sub-orders where the marketer is the seller (own FBN listings) are settled daily at 03:20 like vendor sales (gross − coupon − category commission − VAT on commission − gateway share − peer promoter commission − shipping contribution − refunds). The net goes to the pending balance and is released after the clearing days. |
| **Delivery agent payout** | generate → approve → process (per-delivery rates + bonuses). |
| **Acquisition commission** | Monthly, on the 1st at 02:00. The admin who brought in a vendor earns a rate on that vendor's sales within a min/max band. |
| **Wallets** | One wallet per owner and currency (customer, vendor, delivery agent, travel agency, marketer). `wallet_transactions` is append-only and records the balance after each movement. Admin can freeze a wallet. |
| **Ledger** | Double-entry: every financial event writes balanced debit and credit rows in one transaction group. |
| **Subscriptions** | Vendor plans: `trialing → active → past_due → cancelled | expired`. Invoices are generated monthly. |

Every payout requires an admin approval step; nothing is paid out automatically.

---

## 10. Platform Marketing and Advertising

| Feature | Lifecycle / rules |
|---|---|
| **Coupons** | Types: percentage, fixed_amount, free_shipping, bogo. Scopes: platform, vendor, category, product. At most 1 platform + 1 vendor coupon per order. Expired coupons are deactivated daily at 00:15. |
| **Coupon participation** | Admin opens an invitation (minimum fee, capacity, expiry) → vendors and marketers request to join with an offered fee ≥ the minimum → admin approves (wallet charged) or confirms a bank transfer → `paid`. A rejection refunds a paid fee. Expired invitations close every 15 minutes, and fulfilled ones are activated daily. |
| **Flash sales** | Event: `draft → submission_open → submission_closed → under_review → approved → live → ended | cancelled` (transitioned every 5 minutes). A vendor submission must be below the original price: `draft → submitted → under_review → approved | rejected → live → sold_out | ended | withdrawn`. Marketers can be invited (`pending → accepted | declined`) and earn a **flash-sale bonus** on conversions during the live event. Prices revert automatically at the end. |
| **Banners** | Placement-based and scheduled (every 5 minutes): `scheduled → active → expired`, or inactive. |
| **Ad campaigns (self-serve)** | CPC or CPM, keyword, product and category targeting: `draft → pending_review → active ⇄ paused → budget_exhausted | ended | rejected`. The daily budget resets at 00:00. Fraud patterns can be blocked. |
| **Paid ad bookings (Nawi Ads)** | Advertiser is a vendor or a **marketer**: `draft → pending_review → approved → scheduled → active ⇄ paused → completed`, or `rejected | cancelled | expired`. The creative is reviewed separately. Scheduler runs every 5 minutes. |
| **Gift cards / vouchers** | Admin-issued; redeemed fully or partly at checkout; expired daily at 00:30. |
| **Cart card offers** | Card-specific offers shown in the cart. |
| **Newsletter, push notifications** | Broadcast channels. |

---

## 11. Marketers and Marketer Campaigns

This section describes the affiliate/influencer system in full. It is separate from the self-serve **Ad Campaigns** and **Paid Ad Bookings** in §10, which have no referral or commission logic.

### 11.1 Marketer types (v3 model)

| Type | How they are paid | Who picks them |
|---|---|---|
| **Influencer** | **One fixed amount per campaign**, never per sale. Paid when the campaign completes, if they are still on it. | Picked by name by the campaign owner. Replacements are automatic. |
| **Affiliate** | **Percentage × sale basis**, for each sale. | Invited by name, or joins a campaign that is **open to all affiliates/brokers** of the country. |
| **Broker** | **Fixed per item + percentage × basis** (either part may be empty). Also receives matched customer **special requests**. | Same as affiliate. |

A marketer can hold several types. Their **effective type on a campaign** is decided as follows: an invitation with an influencer fixed snapshot is an influencer slot; a marketer who is both broker and affiliate acts as broker; an influencer-only marketer acts as influencer; legacy accounts act as affiliate.

**Commission basis** (in the campaign currency):
- Product: item price **excluding VAT, after discounts**, for the whole line.
- Classified: the sold price.
- Travel: the booking total.

### 11.2 Marketer onboarding lifecycle
```
Register (marketer.) → Onboarding: pick job type(s) → profile
   → global_status = pending → Admin approve | reject (or later suspend / activate)
   → Accept the onboarding contract (if the admin uploaded a version)
   → may accept campaigns
```
- **FR-MKT-1** A marketer cannot accept an invitation unless `global_status = active` **and** they have accepted the current onboarding contract version (`MarketerContract`).
- **FR-MKT-2** Admin configures per-marketer **profile commission rules** per category or vertical: the influencer fixed amount, the affiliate percentage, the broker fixed and percentage amounts, and an optional fee override. When no rule exists, the platform defaults on **Marketer Settings** apply (country × vertical × category).

### 11.3 Campaign actors and origins

| # | Origin | Owner | Item | Initial status | Who approves |
|---|---|---|---|---|---|
| A | **Vendor → marketers** | Vendor (product or classified) | Own vendor listing, or own active classified ad | `pending_admin` | Admin, or auto-approve after `marketer_campaign_auto_approve_hours` (default 36 h) |
| B | **Travel agency → marketers** | Travel agency | Own active trip with seats left that has not departed | `pending_admin` | Admin, or auto |
| C | **Platform → marketers** | Platform (admin) | Admin listing | `pending_admin` | Admin, or auto |
| D | **Marketer → vendor/platform (request)** | Marketer (requester) | A vendor listing or admin listing | `marketer_requested` | The **listing owner** (vendor in the partner panel, admin for platform listings) |
| E | **Marketer → marketer (peer promotion)** | The influencer who owns the listing | Another influencer's own FBN listing | `active` **at once** | None (the rules are enforced automatically) |
| F | **Affiliate/broker joins an open campaign** | — | Any live campaign with `open_to_affiliates = true` in their country | joins as `accepted` | None |

The FBN/FBM listings that may host campaigns are set by the setting `marketer_campaign_allowed_fulfilment_models`.

### 11.4 Campaign creation wizard (owners A, B and C)
The wizard is the same for every owner and vertical (CampaignWizardService). The owner enters:

- **Marketers**: influencers of the item's country. A marketer already tied to another live campaign of the same item cannot be picked.
- **Open to affiliates**: when on, every eligible affiliate and broker in the country can join.
- **Max Commission Budget**: optional. Blank or 0 means **unlimited**, and then an **end date is required**. A budget that is given must be **strictly greater** than the total of the picked influencers' fixed amounts.
- **End date**, title, notes, selected ad types, vendor ad notes.

On submit:
1. The source is validated: ownership, an allowed fulfillment model, and for products **stock after samples ≥ category `min_stock_for_campaign`** (default 10).
2. **Samples are planned**: one platform sample, one per influencer, and one affiliate row if affiliates are targeted (§11.9).
3. The **campaign fee is snapshotted** server-side (§11.10). The owner sees a live quote (`/quote`).
4. **Influencer fixed amounts are frozen and reserved** from the budget. The campaign is rejected if they do not fit.
5. If **collaboration contracts** apply to the picked marketer types in the seller's country, the seller must sign them first (§13.5).
6. Admins are notified and the owner gets a "pending" notice. `ProcessCampaignAutoApproveJob` is scheduled.
7. The promoted item gets `campaign_enabled = true`.

### 11.5 Campaign status lifecycle

```
                         ┌──────── reject (admin, with reason) ───────► rejected
                         │
 create ─► pending_admin ┼──── approve (admin) ───────────► active ─────────┐
   (A,B,C)     │  edit    │                                                 │
               │  cancel  └── auto-approve job (after N hours) ─► auto_approved
               ▼                                                            │
           cancelled                                                        │
                                                                            ▼
 request ─► marketer_requested ── owner approves (sets budget) ─► active    paused
   (D)            └── reject ─► rejected                         ▲    │      │
                                                                 │    │      │
 peer (E) ─────────────────────────────────────────────► active  │    │      │
                                                                 │    ▼      │
  active / auto_approved ── low stock / item paused / owner pause ──► paused
  paused ── stock back / item active / owner resume (if budget left and before end date) ──► active
  active / auto_approved / paused ── stock 0 / budget used up / end date / classified sold
        / trip sold out, expired or departed ──► done
  active (owner cancels) ──► cancelled
```

| Status | Meaning |
|---|---|
| `pending_admin` | Waiting for admin review. The owner can edit (title, notes, ad types, end date, budget) or cancel. No invitations exist yet, so a marketer never holds a live link to a campaign that is later rejected. |
| `marketer_requested` | A marketer asked to promote this listing. Waiting for the owner. |
| `active` / `auto_approved` | Live. Invitations are dispatched and the campaign fee is charged. |
| `paused` | No new attributions; marketer listings are paused. Caused by low stock, the item being paused, or the **owner** (`owner_paused_at`; only the owner can lift an owner pause). |
| `done` | Finished: pending invitations are cancelled, marketer listings archived, **influencers settled**, owner and marketers notified. |
| `cancelled` | Owner cancel (while pending, or an **active** campaign via `cancel-active`). Influencers are **not** paid. The reserved budget is released and an unpaid campaign fee is voided. |
| `rejected` | Admin reject, with the same cleanup as cancel. |

Terminal statuses are `cancelled`, `rejected`, `done` (and the legacy `completed`). Only pending or terminal campaigns can be (soft) deleted. `campaign_enabled` on the item stays true while any non-terminal campaign exists for it.

**Availability rules** (hourly `MonitorCampaignStockJob`, plus the `ListingStockChanged` listener):

| Vertical | Done when | Paused when | Resumed when |
|---|---|---|---|
| Product | stock = 0 | stock < `min_stock_for_campaign` | stock ≥ minimum, budget left, not past end date, not owner-paused |
| Classified | listing sold, expired or rejected | listing paused | listing active again |
| Travel | sold out, expired, departed, or 0 seats | trip pulled from sale | trip active again |

In addition, `CompleteEndedMarketerCampaignsJob` (daily at 04:00) completes campaigns whose end date has passed. A campaign is also marked **done when its budget is used up**.

### 11.6 Marketer ↔ Marketer: peer promotion (influencer promotes influencer)
An influencer can promote **another influencer's own FBN product listing**. The rules (all enforced in `assertPeerPromotionAllowed`):

- The listing must be the owner influencer's **own** FBN listing (`source_listing_id IS NULL`, category product).
- Promoter and owner must **both be influencers**; **self-promotion is blocked**.
- The listing must be `active`, with stock ≥ `marketer_peer_campaign_min_stock` (default 1).
- **One promotion per (promoter, owner) per calendar month.** Rejected or cancelled promotions free the slot. The promoter row is locked so that two simultaneous requests cannot both pass.

Flow:
```
Promoter: Peer Products → Request campaign (marketer_listing_id)
  → campaign created: owner_type = marketer, owner_id = listing owner, requested_by = promoter,
    country and currency taken from the listing, no seller review, no campaign fee
  → status = active immediately
  → promoter's invitation dispatched and auto-accepted (no WhatsApp, no timeout)
  → proxy marketer listing created that sells the OWNER's stock at the OWNER's price
  → owner notified (PeerPromotionStartedNotification) and sees it under "Promoted by Others"
```
Money:
- The promoter earns **per sale** under their **profile rule** for the category. This is the one exception to "influencers never earn per sale". Without a rule, the promoter earns 0 (`hasNoPeerCommission`).
- The **owner influencer is the seller** of the sub-order and **pays the promoter's commission** out of their sale settlement (`marketer_commission_owner = marketer`), along with the platform category commission, VAT on commission, gateway share and so on (§9).

### 11.7 Marketer ↔ Vendor: invitations, acceptance, replacement

**Dispatch** (on approval, auto-approval, invite-more, replacement, owner approval of a request, or joining an open campaign):
- A new invitation is created with status `pending`, `expires_at = now + marketer_invitation_timeout_hours` (default 12 h), a **fresh** 10-character referral code, link `{frontend}/r/{code}` and a QR PNG. Codes are never reused.
- The influencer's frozen fixed amount is copied onto the invitation (`influencer_fixed_status = reserved`).
- If the seller signed an **open-call collaboration contract**, the invitation is covered by it.
- The influencer's sample row is created (§11.9).
- Notifications go out in-app and by **WhatsApp**. The WhatsApp link is signed and lets the marketer accept or reject without logging in. Admin can resend it, with a cooldown (default 10 minutes).

**Invitation statuses:** `pending → accepted | rejected | timed_out | cancelled`. A replaced invitation keeps its real status and gets `replaced_at`, and the new one points back through `replaced_invitation_id`.

**Accept** (marketer):
1. The account must be active and the contract accepted.
2. Status becomes `accepted`, with an optional marketer note.
3. A **marketer listing** for the product, classified or trip is created with the referral code.
4. The owner is notified in-app and by WhatsApp.

**Reject / timeout → automatic replacement:**
1. A candidate must have the same job type, be active, be in the same country, never have been invited to this campaign, and have accepted ≥ `marketer_replacement_min_accepted_campaigns` campaigns.
2. If the slot is an influencer slot, the candidate must be an influencer **and** their own fixed amount must fit the remaining budget (the predecessor's reservation is released first).
3. Candidates are ordered by **fewest invitations in the last 30 days**, then fewest accepted campaigns, to spread work evenly.
4. Open campaigns do not replace affiliates or brokers, because every eligible one was already reached.
5. The undispatched sample is handed to the replacement or cancelled.
6. Notices go to the rejected marketer, the owner ("rejected/timed out — replacement searched", and "replaced by X") and the admins (reassignment log).
7. If no replacement is found, the campaign continues with the marketers who remain.

**Invite more:** allowed while the campaign is `pending_admin` or `active`. Marketers already pending or accepted, inactive marketers, and influencers who do not fit the budget are skipped, each with a reason.

**Marketer-originated request (D):** the marketer proposes a vendor or admin listing → `marketer_requested` → the owner (vendor via `approve-request`, or admin for platform listings) **sets the Max Commission Budget (> 0 required)** → ownership flips to the real owner, a fee snapshot is taken for vendors, an influencer requester's fixed amount is reserved, and the requester's invitation is dispatched and **auto-accepted** → `active` → the campaign fee is charged.

### 11.8 Attribution and referral codes

**Click tracking:** a visitor who opens `/r/{code}` has the click recorded against their session or customer for `marketer_attribution_window_days` (default 7).

**Who gets credit for an order item** (LastClickAttributionService):
1. **Direct**: the item was bought from a marketer listing that came from an accepted invitation. That invitation gets the credit, no click needed.
2. **Last click**: the latest recorded referral click inside the window.
3. Otherwise, no attribution.

**Referral code validity matrix** (ReferralCodeResolver):

| Invitation | Campaign | Result |
|---|---|---|
| accepted | active / auto_approved | **ACTIVE**: attributable |
| pending | live | **PENDING**: click remembered, not attributable until accepted |
| rejected / timed_out / replaced, closed less than `marketer_referral_grace_days` ago | live | **GRACE**: attributable only for clicks recorded **before** it closed |
| rejected / timed_out / replaced, past the grace period | — | **EXPIRED** |
| any | cancelled / done / paused / … | **CAMPAIGN_CLOSED** |
| unknown code | — | **UNKNOWN** |

**Non-product verticals** (ListingReferralService): a classified inquiry or a trip booking is stamped with the marketer, campaign and code **only if the click was for that same item**. A click for trip A never credits trip B.

### 11.9 Promotion samples
- **Platform sample**: one per product campaign, created with the campaign. Quantity = category `platform_sample_qty` (at least `marketer_campaign_min_platform_sample_qty`, default 1).
- **Influencer sample**: one per influencer, created when the invitation is dispatched. Quantity = category `influencer_sample_qty` (at least 1).
- **Affiliate sample**: a single platform-owned row (`affiliate_sample_qty`) when affiliates are targeted.
- The marketer submits a delivery address, the admin updates the sample status (dispatch and so on), and the marketer confirms receipt. The vendor can fill in the sample's custom attributes.
- **Accounting:** a sample leaves stock like a sale, and the platform sale commission on price × quantity is charged to the seller's next statement as a zero-priced line. Undispatched samples are cancelled when a campaign is rejected, cancelled or done.

### 11.10 Money: budget, campaign fee, commission fee

**Campaign fee**, paid by the owner (vendor or travel agency) to the platform. It is snapshotted at creation and never comes from client-side numbers:
```
base          = Max Commission Budget  (for an unlimited campaign: the influencers' fixed total)
fixed_fee     = fixed_amount(country)            + base × fixed_%(country)
variable_fee  = amount(country, category, type)  + base × %(country, category, fulfilment type FBM/FBN/FBP)
                (classified and travel: per country × vertical)
campaign fee  = fixed_fee + variable_fee        (no VAT; rounded half up to 2 dp)
grand total   = base + campaign fee
```
- The fee is **charged when the campaign goes live**: from the owner's wallet if it covers the fee; otherwise `campaign_fee_status = pending`. A vendor's pending fee becomes a `campaign_fee` line on the next payout statement. An agency's pending fee is retried nightly against its wallet.
- A pending fee is **voided** on reject or cancel. A **paid fee is not refunded**.
- Platform-owned and marketer-owned (peer) campaigns pay no fee.

**Budget:**
- Budget used = `commission_budget_spent` (affiliate and broker commissions) + `influencer_fixed_reserved`.
- The budget is never exceeded: the last commission is shortened to whatever is left, and the campaign becomes `done` as soon as nothing useful remains.
- An unlimited budget runs until the end date.

**Commission fee**, the platform's cut of each marketer commission:
```
fee = percentage × gross + fixed     (capped at gross)
net = gross − fee                    (never below 0)
```
Each field (percentage, fixed) is resolved by taking the first non-null value from: the marketer's profile rule for the category, then the profile rule for the vertical, then the platform default (category row, then vertical row, per country).

**Influencer fixed amount** (InfluencerFixedService):
1. **snapshot**: the amount is frozen at creation. Source order: invitation snapshot, then profile rule (category, ancestors, vertical), then platform default.
2. **reserve**: the snapshot total is held from the budget.
3. **applyTo**: the amount is copied onto the invitation when it is dispatched.
4. **release**: the amount returns to the budget if the invitation is rejected, timed out or cancelled, or if the campaign ends before acceptance.
5. **settle**: at **campaign done**, every still-accepted influencer is paid **net of the commission fee directly into the spendable balance**, and the fee goes to the platform ledger. This is idempotent (`influencers_settled_at`).

### 11.11 Conversions lifecycle

**Conversion types:** `product_sale`, `classified_sale`, `travel_booking`, `classified_inquiry` (a lead only; never auto-approved).

```
Sale / booking / inquiry recorded (CampaignSaleRecorder, under a campaign row lock)
   → one conversion per source, ever (idempotent)
   → gross / fee / net computed and snapshotted; budget consumed
   → status = pending
ApproveMarketerConversionsJob (daily 03:15):
   product_sale:    sub-order delivered/completed AND the item's return window passed
   classified_sale: marketer_classified_approval_days (default 2) after the sale
   travel_booking:  booking completed
   → status = approved → NET credited to the marketer's PENDING balance
ReleaseMarketerPendingCommissionJob (daily 03:30), after marketer_payout_clearing_days:
   → pending → spendable balance, wallet history line, ledger group:
        Dr seller_payable (vendor) / platform_revenue (platform campaign)   gross
        Cr marketer_payable (marketer)                                      net
        Cr platform_commission (marketer_commission_fee)                    fee
Cancellation / return / booking cancel / classified un-sold:
   → status = reversed, wallet (pending or spendable) taken back, ledger mirrored,
     budget freed (a finished campaign is NOT reopened)
   → a conversion already PAID in a payout is never clawed back automatically (manual recovery)
```
Influencers do **not** get per-sale conversions on normal campaigns; only peer promoters do. The **flash-sale bonus** is added when the sale happens during a live flash sale the marketer accepted.

### 11.12 Classified and travel campaign specifics
- **Classified:** the platform only learns about the sale when the seller marks the ad **sold**. While a campaign is live, the seller must enter the **final sold price** and can choose which affiliate or broker brought the buyer (the list shows how many inquiries each one referred) and add a buyer note. That marketer gets a `classified_sale` conversion, and the listing's open campaigns are completed (influencers are paid). Un-marking the sale reverses the conversion, but a campaign completed by the sale is not reopened, and the sale cannot be taken back once the commission has been paid out.
- **Travel:** a booking made through a marketer link carries marketer_id, campaign_id and code. **Confirmed** (the agency confirms once the traveller has paid) creates the conversion, **completed** (set nightly) lets it be approved, and **cancelled** reverses it.

### 11.13 Other marketer features
| Feature | Description |
|---|---|
| **Own listings and selling** | See §5.4. Marketers have orders, inventory and FBN stock; marketer-seller settlement is described in §9. |
| **Customer chat** | Real-time marketer ↔ customer conversations. One write path for web, app and panel; unread flags; broadcast. |
| **Special requests (brokers)** | A customer posts a special request (for example, "looking for a 3-bedroom villa") → active brokers whose profile matches are notified → a broker **starts** it (atomic `open → in_progress`, so only one broker can claim it). |
| **Exclusive contracts** | Admin grants a marketer exclusivity on a **classified listing** or a **whole classified category** for a date window, with a private contract file. The storefront shows the active exclusive marketer on the listing (the listing-level grant wins over the category-level one). Expired daily at 00:45. |
| **Ad packages** | Marketers subscribe to paid packages: by wallet, it becomes `active` at once; by bank transfer with proof, it is `pending` until the admin approves (then `active` until `expires_at`) or rejects. Expiry runs hourly. |
| **Promote (paid ad slots)** | Marketers book Nawi ad slots like vendors do (§10). |
| **Public directory** | Storefront `/marketers` and `/marketer/[slug]`, with the profile's listings and the contracts shown on the profile. |
| **Monthly obligations** | Peer promotion is part of an influencer's monthly obligation (one per owner per month). The admin Celebrity Monthly Report and monthly quota labels exist in the translations. |

---

## 12. Classifieds and Travel

### 12.1 Classified listing lifecycle
```
draft → pending_contract (seller must sign the category contract)
      → pending_review → admin approve → active ⇄ paused → sold | expired
                       └─ reject → rejected
```
- **FR-CLS-1** Digital contract signing happens **before** submission, not after approval.
- **FR-CLS-2** A listing is **never public before admin approval**.
- **FR-CLS-3** Categories are dynamic, each with an optional dedicated website section. Seller contracts and customer purchase contracts are assigned per category and country, and a section inherits from its parent.
- **FR-CLS-4** Inquiries: `new → contacted → closed`. Guests can send them (throttled at 5/min). If a **customer purchase contract** applies, the inquiry or purchase is refused with `customer_contract_required` until the customer accepts the current version.
- **FR-CLS-5** There is a map view (lat/lng, GeoJSON endpoint), wanted listings, and exclusive marketers.
- **FR-CLS-6** Attachments are verified by the admin.

### 12.2 Travel
```
Agency: register (portal) → admin approve (pending → active ⇄ suspended)
Package: draft → pending_review → admin approve → active → sold_out | expired
Booking: pending_documents → confirmed → completed   (or cancelled)
Inquiry: new → contacted → converted | closed
```
`CompleteTravelBookingsJob` (daily at 03:45) completes past trips and retries pending agency campaign fees. Locked agency profile sections change only through admin-approved change requests.

---

## 13. Contracts and Documents

### 13.1 Contract kinds
All kinds share template storage and versioning. Each kind has **its own resolver, signing service and signature table**.

| Kind (`category_scope`) | Parties | Signed when | Signature record |
|---|---|---|---|
| **seller_platform** (Seller Agreement) | Seller ↔ platform | At registration (last step), then on each new version | Vendor contract acceptance |
| **seller_category** (individual product / classified contract) | Seller ↔ platform | Before the **first** listing in that category or product, once per subject and version | `vendor_contracts` |
| **seller_marketer** (collaboration) | Seller ↔ influencer, celebrity or broker | When the seller creates or approves a marketer campaign | `marketer_seller_contracts` (`active / superseded / revoked`) |
| **customer_purchase** | Customer ↔ seller/platform for buying or renting a classified item | Before the inquiry or purchase action on the ad | `customer_contracts` (`active / superseded / cancelled`; purpose purchase or rental) |

Separate from these:
- **Marketer onboarding contract** (`MarketerContract` and its versions/acceptances): marketer ↔ platform. It must be accepted before the marketer takes any campaign. It can be marked *required for customers*, in which case buyers accept it before checking out a line from that marketer.
- **Exclusive contracts** (§11.13).

### 13.2 Template authoring
- **Mode:** **editor** (Arabic and English text, with auto variables such as store name, commercial registration number, address and date) or **uploaded file** (PDF/DOC/DOCX, ≤ 20 MB, Arabic required and English optional; Word files are converted to a PDF preview when the server supports it). In file mode, variables do not apply; the signer's name and date are recorded on the signature, not on the file.
- Files are stored on a **private disk** and only served through authorized controllers.
- **Country:** a template has a country, or none (legacy/global). The country's own template wins, and the legacy one serves countries without their own. Resolution **never falls back to another country's template**.
- The scope is chosen at creation and cannot be changed.

### 13.3 Template lifecycle
```
Draft ──publish──► Published ──(new version published)──► Retired
                     └── (notice mode, effective date in future) = Scheduled
```
- A **published version is never edited**. Editing creates a new draft (v2, v3…).
- Nothing reaches sellers before **Publish**.
- Links to categories belong to the contract lineage: they point at the published version (or at the draft while nothing is published) and move along when a newer version is published. Links to a draft are inert.
- `contracts:apply-effective` runs **hourly** to activate scheduled versions.

### 13.4 Seller Agreement consent modes
- **Click-wrap (mandatory):** the vendor is blocked on the partner web panel until they sign. This is used for material changes and always for the first version.
- **Notice:** the vendor gets an email and in-app notice with the effective date (for example +14 days) and a change summary. After that date, continued use counts as consent and is recorded with the method *continued use*. Notice mode is available for the Seller Agreement only; category contracts always need explicit signing.

### 13.5 Contract resolution for sellers (ContractResolver)
1. The seller's country.
2. The active contract for that country.
3. Does the product or category require an individual contract? A product can be *required*, *exempt* or *inherit*; a category switch is inherited from its parents.
4. If yes: the product's own assignment, then the nearest requiring category, then the country's default for that scope. **If none is configured, `ContractNotConfiguredException` is raised**; it is never silently replaced by the global agreement.
5. Otherwise: the country's Seller Agreement.

A newer published version makes existing signatures stale (the enrollment moves to `re_sign_required`). Signatures are never deleted or edited.

**Collaboration contracts (seller_marketer):**
- Applicable templates are those in force for the `marketer` scope, then those linked to specific marketers or to marketer job **destinations** (Display On), then those for the seller's country (the country's own, else legacy), optionally narrowed by classified category, keeping the newest version per contract name.
- When nothing applies, the collaboration proceeds. The admin page lists marketer types that have no contract (`uncoveredMarketerDestinations`), so a gap is visible.
- An **open-call** collaboration contract signed by the seller covers marketers who join later.

### 13.6 What is stored as evidence on each signature
The typed full name; date and time; IP address and user agent; version and language; a **frozen copy** of the text or file; a **SHA-256 hash**; and the consent method (at registration / click / continued use). A copy is emailed to the seller, and the signed copy can always be downloaded from the panel. The system makes no claim about the legal standing of the electronic signature.

### 13.7 Documents
| Document | Owner | Lifecycle |
|---|---|---|
| **Vendor onboarding documents** (commercial registration, license…) | Vendor | Requirement per country and document type: mandatory / optional / not_applicable (admin **Document Types**). Uploaded at registration step 4 and in Settings → Documents. Status `pending → approved | rejected`. |
| **Product certifications** | Vendor | Required to list certain products; reviewed by the admin. |
| **Marketer documents** | Marketer | Identity and profile documents. |
| **Delivery agent documents** | Agent | Verified by the admin before activation. |
| **Classified attachments** | Seller | Verified by the admin during review. |
| **Travel package contracts** | Agency | Downloadable by the admin during review. |
| **Exclusive contract files** | Admin | Private file, downloadable by the admin and the marketer. |
| **Ad package / coupon participation proofs** | Marketer or vendor | Bank-transfer proof reviewed by the admin. |

---

## 14. Live Streams and Scheduled Audio/Video Channels

The platform has two kinds of stream, both managed at **Admin → Content → Live Streams** and shown on the storefront at `/live-streams`.

### 14.1 Live broadcast (`source = live`)
- **FR-LS-1** Admin creates a stream: bilingual title and description, thumbnail, optional `scheduled_at`, and **media mode** `audio | video | both`. A random `stream_key` is generated.
- **FR-LS-2** The admin **studio** captures camera and/or microphone in the browser and presses **Go Live**: status becomes `live`, `started_at` is set, and a "hello" signal tells connected viewers to (re)negotiate.
- **FR-LS-3** Media flows over **WebRTC peer-to-peer** (admin → one connection per viewer, a mesh suited to about 30–50 concurrent viewers). Signalling (SDP offer/answer, ICE) goes through REST plus **Reverb** WebSockets. **coturn** relays media when P2P is impossible (mobile CGNAT, firewalls).
- **FR-LS-4** **End** sets status `ended` and `ended_at`. A stream that has ended cannot go live again.
- **FR-LS-5** Viewers (public, no login) can **comment** and **like** (throttled at 30/min). Signalling is throttled at 240/min for viewers and 600/min for the admin. Admin can view and delete comments. Peak viewers, total viewers and likes are tracked.

### 14.2 Scheduled audio/video channel (`source = recorded`, "radio channel")
- **FR-LS-6** Admin uploads a media file (mp3, wav, ogg, oga, m4a, aac, mp4, webm, mov, m4v or mpeg; ≤ 500 MB) and sets **`scheduled_at` (required)**.
- **FR-LS-7** Duration is read with **ffprobe**; if ffprobe is unavailable, the duration measured in the browser is used. If no duration can be determined, the upload is refused. `scheduled_end_at = scheduled_at + duration`.
- **FR-LS-8** Lifecycle, run **every minute** (`streams:sync-recorded`) and lazily by the public API:
  ```
  scheduled ──(now ≥ scheduled_at)──► live ──(now ≥ scheduled_end_at)──► ended
  ended but re-timed to a future date ──► scheduled again (healed automatically)
  ```
- **FR-LS-9** **Go Live is refused** for channels; they start and end on their own schedule.
- **FR-LS-10** **Playback mode:**
  - `synced` (default): every viewer hears or sees the same moment, like radio. The playback position is `now − scheduled_at`, capped at the duration.
  - `free`: each viewer plays from the start.
- **FR-LS-11** The public API (`/api/public/v1/streams`) returns the media URL, playback mode and current offset.

### 14.3 Deployment requirements
HTTPS for all hosts; `ws.` host for Reverb (8080, internal only) and coturn (3478 UDP/TCP, 5349 TLS, relay range 49152–65535 UDP). Under Cloudflare, `ws` must be DNS-only. Never run `config:cache` or `optimize`. See `docs/LIVE_STREAMS_VPS_*.md`.

---

## 15. Content, Support and Notifications

| Feature | Description |
|---|---|
| **Page builder** | Drag-and-drop blocks (hero slider, ad images, category chips, brands, product grid and carousel, flash-sale countdown, vendor spotlight, HTML, banner), per-block visibility, block analytics. Page status `draft → published / scheduled → archived` (scheduler every 5 minutes). Revisions are **append-only** and can be restored. Pages can be **copied to several countries**. |
| **App contexts** | Mobile top strip (Main, Super Mall, Food, 15-Minute Delivery…), with countries and bottom navigation. |
| **Static pages** | Country-specific with a global fallback. |
| **Portal content** | About, Terms, Privacy, Return Policy, Vendor Agreement pages. Edit access is controlled by a permission. |
| **Blog** | Nested categories, bilingual posts, tags, featured flag, attachments, view counter, scheduled publishing (every 5 minutes). |
| **Knowledge hub / help center / FAQs** | Vendor knowledge base (pinned articles), customer help center, FAQs (drag to reorder). |
| **Footer settings** | Storefront footer. |
| **Support tickets** | For customers, vendors, marketers and agencies: `open → in_progress → waiting_customer → resolved → closed`. |
| **Notifications** | Database + mail + broadcast (Reverb) + **FCM push** + **WhatsApp** (campaign invitations and responses). A notification bell is in every panel. |
| **Credential emails** | Credentials and passwords are emailed to newly created accounts (client requirement 9). |

---

## 16. Roles and Permissions

- Spatie Permission is applied **per guard**: admin, vendor, travel agency, marketer, delivery agent and carrier supervisor each have their own roles and permissions.
- **Admin:** system roles cannot be deleted; custom roles can be built from any permissions, using the format `{module}.{action}` (for example `marketer_campaigns.approve`). A restriction permission limits an admin to their assigned vendors and hides financial data.
- **Vendor:** roles are scoped per vendor by a prefix and checked with `vendor.can:*`. The vendor owner creates custom roles in Partner → Roles.
- **Travel agency:** roles are scoped per agency. The owner bypasses checks, and members are checked through `travel_agency.can:*`.
- Tenant-scoped role names never collide, because of the prefix.

---

## 17. Scheduled Jobs

| Schedule | Job | Effect |
|---|---|---|
| every minute | ReleaseExpiredLocksJob | Releases 15-minute cart stock locks |
| every minute | `streams:sync-recorded` | Scheduled audio/video channel transitions |
| every 5 min | TransitionFlashSaleStatusJob, FlashSaleSchedulerJob | Flash-sale states |
| every 5 min | BannerSchedulerJob, PageSchedulerJob, PublishScheduledBlogPostsJob | Content schedules |
| every 5 min | PaidAdSchedulerJob, ExpirePendingPaymentsJob | Ad bookings, stale payments |
| every 15 min | CheckSlaBreachJob | Delivery SLA |
| every 15 min | `coupons:close-expired-participation-invitations`, `promotion:expire-items` | Coupons and promotions |
| hourly | MonitorCampaignStockJob | Campaign pause / resume / done |
| hourly | `ad-packages:expire`, `contracts:apply-effective`, AggregateAnalyticsCacheJob | — |
| daily 00:00 | ResetAdCampaignDailyBudgetJob | — |
| daily 00:15 / 00:30 / 00:45 | Expire coupons / gift cards (and activate fulfilled coupon invitations) / exclusive contracts | — |
| daily 01:00 / 01:15 | FBN daily overage / flag overstored unpaid | — |
| daily 02:00 | AutoCompleteOrdersJob | delivered → completed |
| daily 03:00 | ExpireWarrantyPurchasesJob | — |
| daily 03:15 | ApproveMarketerConversionsJob | pending → approved |
| daily 03:20 | SettleMarketerSalesJob | Marketer-seller sub-orders |
| daily 03:30 | ReleaseMarketerPendingCommissionJob | Pending → spendable |
| daily 03:45 | CompleteTravelBookingsJob | Bookings completed; agency fee retry |
| daily 04:00 | CompleteEndedMarketerCampaignsJob | End date reached → done |
| daily 04:30 | `portal-analytics:prune` | — |
| daily 23:30 | GenerateCodSettlementsJob | — |
| weekly Mon 02:00 / 1st & 15th / monthly 1st | Generate vendor payouts | Per payout schedule |
| monthly 1st 02:00 / 07:00 | Acquisition commissions / FBN storage fees | — |
| weekly | FbnInboundReminderJob, best-seller rankings | — |
| delayed (per item) | ProcessCampaignAutoApproveJob, ProcessInvitationTimeoutJob | Auto-approve after N hours; invitation timeout |

---

## 18. Non-Functional Requirements

| ID | Requirement |
|---|---|
| NFR-1 Money integrity | Never sum across currencies; snapshot amounts at the moment of the event (campaign fee, influencer fixed amount, conversion gross/fee/net, packaging unit cost, sale settlement terms); use row locks and unique keys for idempotency (conversions, settlements, payouts). |
| NFR-2 Auditability | Activity log, append-only ledger, wallet transactions, inventory movements, revisions and signatures. Order status history records the actor. |
| NFR-3 Security | Guard isolation per panel; permission middleware on every route; private disk for contracts and documents; signed URLs for WhatsApp responses; throttling on public endpoints. |
| NFR-4 Localization | AR/EN everywhere, with RTL. Storefront URLs use `{country}-{lang}`. |
| NFR-5 Real-time | Reverb for notifications, chat and stream signalling; a queue worker is required for mail, WhatsApp and notifications. |
| NFR-6 Availability of jobs | The Laravel scheduler must run every minute, and `mp-queue` must always be running. |
| NFR-7 Performance | Navigation badge counts are cached; page-builder cache is cleared on save; live-stream mesh is limited to about 30–50 viewers per broadcast. |
| NFR-8 Data safety | Migrations are append-only; destructive database commands are forbidden on the live database. |
| NFR-9 Testing | PHPUnit feature tests and Playwright end-to-end tests per panel (`backend/tests/{admin,vendor,…}`). |

---

## 19. Known Gaps and Differences

These were found while writing this SRS. Each one should be confirmed with the product owner.

1. **Attribution models.** The `AttributionModel` enum (`last_click`, `first_click`, `linear`) and the in-app Marketer Campaigns doc describe three models, but the code only uses **direct listing credit, then last-click**. First-click and linear are not implemented.
2. **Old campaign audit is partly out of date.** `docs/marketer-campaign-flow.md` (2026-10-04) lists as missing: admin commission editing, approve-request, marketer request, cancel-active, and notifying the rejected marketer. All of these **now exist** (routes `approve-request`, `campaigns/request`, `cancel-active`, and `CampaignInvitationRejectedNotification` to the marketer). The v3 model also replaced the typed per-campaign commission with per-marketer-type rules.
3. **Retired features still in the docs.** Influencer Deals, Secret Promotions and Affiliate Promo Codes have in-app doc pages but **no active admin routes or menu items**.
4. **Mobile click-wrap.** The partner Flutter app does not yet block a vendor who has not signed a new mandatory Seller Agreement; only the web panel does.
5. **Paid conversions are not clawed back.** A cancellation or refund after a marketer payout needs manual recovery.
6. **A paid campaign fee is never refunded,** even if the campaign is cancelled right after going live.
7. **Live-stream scale.** The P2P mesh tops out at about 30–50 viewers per broadcast; larger audiences would need an SFU.
8. **Marketer and travel agency registration** still use their older contract flow, not the new country-based seller contract engine.

---

## 20. Appendix: Configurable Settings

### 20.1 Marketer and campaign settings

| Key | Default | Purpose |
|---|---|---|
| `marketer_campaign_auto_approve_hours` | 36 | Hours before a pending campaign auto-approves |
| `marketer_invitation_timeout_hours` | 12 | Invitation acceptance window |
| `marketer_replacement_min_accepted_campaigns` | 0 | Minimum accepted campaigns for a replacement candidate |
| `marketer_attribution_window_days` | 7 | How long a referral click counts |
| `marketer_referral_grace_days` | (setting) | Grace period for a closed invitation's earlier clicks |
| `marketer_payout_clearing_days` | (setting) | Pending → spendable delay |
| `marketer_classified_approval_days` | 2 | Classified sale approval delay |
| `marketer_peer_campaign_min_stock` | 1 | Minimum stock for a peer promotion |
| `marketer_campaign_min_platform_sample_qty` | 1 | Minimum platform sample |
| `marketer_campaign_min_influencer_sample_qty` | 1 | Minimum influencer sample |
| `marketer_campaign_allowed_fulfilment_models` | (service default) | Fulfillment models that may host campaigns |
| `marketer_whatsapp_resend_cooldown_minutes` | 10 | Cooldown on WhatsApp invitation resends |

### 20.2 Per-category campaign fields
`min_stock_for_campaign` (default 10), `platform_sample_qty`, `influencer_sample_qty`, `affiliate_sample_qty`, the Marketers tab (affiliate defaults), the sale commission %, and the individual-contract switch.

### 20.3 Configuration grids
- **Marketer Settings:** influencer fixed amount and commission fee per country × vertical (× category), plus influencer overrides.
- **Marketing Fees:** fixed (per country), variable (per country × category × FBM/FBN/FBP), and vertical (per country × classified/travel).
