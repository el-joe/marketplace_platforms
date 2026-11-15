# Seller Portal (`portal.noon.loc`) — All Pages

> The public site for people who want to **join** Nawy: product sellers, Open Souq (classifieds) sellers, travel agencies, and marketers. It also hosts the **advertising** site, the **Ads Knowledge Hub**, the **Seller Help Center** and the **Blog**.
>
> No login. Source: `backend/routes/portal.php`, `backend/app/Http/Controllers/Portal/`, `backend/resources/views/portal/`. Generated 2026-11-14. The content lists (help center, Knowledge Hub, blog, static pages) come from the local database.

**`{country}`** in a URL is a country site code: `omn` (Oman), `jor` (Jordan), `egy` (Egypt), `uae` (United Arab Emirates), `kwt` (Kuwait), `ksa` (Saudi Arabia), `bhr` (Bahrain), `qat` (Qatar). If you open one of those pages **without** the country part (for example `/helpcenter`), the portal redirects you to the chosen country (from the country switcher), or to the first active country.

## Contents
1. [Page summary](#1-page-summary)
2. [Join & sign-up](#2-join--sign-up)
3. [Product sellers (vendors)](#3-product-sellers-vendors)
4. [Open Souq](#4-open-souq)
5. [Travel agencies](#5-travel-agencies)
6. [Marketers](#6-marketers)
7. [Seller information pages](#7-seller-information-pages)
8. [Advertising (Nawy Ads)](#8-advertising-nawy-ads)
9. [Ads Knowledge Hub (adsupport)](#9-ads-knowledge-hub-adsupport)
10. [Seller Help Center](#10-seller-help-center)
11. [Blog](#11-blog)
12. [Static content pages](#12-static-content-pages)
13. [Legal — Seller Agreement](#13-legal--seller-agreement)
14. [Language & country switchers](#14-language--country-switchers)
15. [Behind-the-scenes endpoints (not pages)](#15-behind-the-scenes-endpoints-not-pages)

## 1. Page summary

| Section | Pages |
|---|---|
| Join & sign-up | 3 |
| Product sellers (vendors) | 3 (landing, 5-step wizard, success) |
| Open Souq | 1 |
| Travel agencies | 3 (landing, 2-step wizard, success) |
| Marketers | 4 (overview + one per type) |
| Seller information pages | 4 |
| Advertising | 6 per country |
| Ads Knowledge Hub | 1 home + 10 collections + 12 articles |
| Seller Help Center | 1 home + 1 search + 9 categories + 14 articles |
| Blog | 1 list + 12 posts |
| Static content pages | 7 |
| Legal | 1 page + downloadable agreement files |

## 2. Join & sign-up

| Page | URL | What it is |
|---|---|---|
| Home | `/` | Redirects to `/join`. |
| Join — choose account type | `/join` | The first page. Pick a section: **Product seller**, **Open Souq**, **Travel agency**, or **Marketer** (influencer / affiliate / broker), plus a country. Admins can hide each card (setting `join_option_{section}_enabled`). |
| Legacy companies link | `/companies` | 301 redirect to `/join`. |
| Legacy register links | `/register`, `/register/success` | 301 redirect to `/vendors/register…`. |

Actions: `POST /join/select` (pick a section → go to its landing page), `GET /join/options` (AJAX: options for a country), `POST /join/continue` (go on to registration).

## 3. Product sellers (vendors)

| Page | URL | What it is |
|---|---|---|
| Vendors landing | `/vendors` | Seller landing page: hero, why sell, how it works, fulfillment models, seller hub, testimonials, FAQ and a call to action. |
| Vendor registration wizard | `/vendors/register` | A 5-step wizard: **1** Account information (name, email, phone, gender, country, password) → **2** Store information (store name & slug, business type and name, CR number, tax ID, description) → **3** Contact & business address (city, street, building…) → **4** Upload official documents (the required list depends on country and category) → **5** Seller agreement & e-signature → submit. |
| Registration success | `/vendors/register/success` | Confirmation after you submit. The application now waits for admin review. |

Actions: `POST /vendors/register/step/{1-3}`, `GET /vendors/register/check-slug`, `GET /vendors/register/cities`, `GET /vendors/register/document-requirements`, `POST /vendors/register/upload`, `DELETE /vendors/register/document`, `POST /vendors/register/complete`, `POST /vendors/register/track` (funnel tracking).

## 4. Open Souq

| Page | URL | What it is |
|---|---|---|
| Open Souq landing | `/companies/open-souq` | An intro to selling classifieds (real estate, cars, services). Its sign-up button uses the vendor wizard at `/vendors/register`. |

## 5. Travel agencies

| Page | URL | What it is |
|---|---|---|
| Travel agency landing | `/companies/travel-agency` | An intro page for travel agencies. |
| Travel agency registration wizard | `/companies/travel-agency/register` | A 2-step wizard: **1** Owner account (name, email, phone, country, password) → **2** Agency details (agency name, license number, logo) → submit. This creates a *pending* travel agency. |
| Registration success | `/companies/travel-agency/register/success` | Confirmation after you submit. |

Actions: `POST /companies/travel-agency/register/step/{1-2}`, `DELETE /companies/travel-agency/register/logo`, `POST /companies/travel-agency/register/complete`.

## 6. Marketers

| Page | URL | What it is |
|---|---|---|
| Marketers overview | `/marketers` | Cards for each marketer type. Each card links to that type's page. |
| Influencer (مؤثر) | `/marketers/influencer` | Promote products and listings for a fixed amount per campaign. Leads on to marketer registration. |
| Affiliate (مسوق بالعمولة) | `/marketers/affiliate` | Earn a percentage of every sale made through your links. Leads on to marketer registration. |
| Broker (وسيط) | `/marketers/broker` | Connect buyers and sellers, for a fixed amount, a percentage, or both. Leads on to marketer registration. |

## 7. Seller information pages

| Page | URL | What it is |
|---|---|---|
| FAQ | `/faq` | Frequently asked questions for sellers. |
| How it works | `/how-it-works` | The steps from sign-up to your first sale. |
| Fulfillment | `/fulfillment` | Fulfillment models: FBM (you ship) and FBN (Nawy warehouse). |
| Smart tools | `/smart-tools` | The seller tools available in the partner panel. |

## 8. Advertising (Nawy Ads)

Every page has a country prefix. Opening it without the prefix (for example `/advertise/brands`) redirects to the right country.

| Page | URL | What it is |
|---|---|---|
| Sellers advertising | `/{country}/advertise/sellers` | Ads landing page for sellers. |
| Brands | `/{country}/advertise/brands` | Ads landing page for brands: solutions and testimonials. |
| Advertisers | `/{country}/advertise/advertisers` | Ads landing page for outside advertisers. |
| Product ads | `/{country}/advertise/product` | Sponsored product ads: listings, a quick guide and an FAQ. |
| Display ads | `/{country}/advertise/display` | Display/banner ads: a quick guide and an FAQ. |
| Request / contact form | `/{country}/advertise/request` | A form to contact the ads team. `POST` saves an advertise request, which admins see in the admin panel. |

## 9. Ads Knowledge Hub (adsupport)

A help site for advertisers. Admins manage it under **Content → Knowledge Hub Collections / Articles**. Every URL has a country prefix; the version without it redirects.

| Page | URL | What it is |
|---|---|---|
| Knowledge Hub home | `/{country}/adsupport` | Top-level collections, their sub-collections, and the featured article. |
| Collection | `/{country}/adsupport/collections/{slug}` | A collection with its sub-collections and published articles. |
| Article | `/{country}/adsupport/articles/{slug}` | The article body and related articles. Opening it counts a view. |

### Current collections & articles (10 collections, 12 articles)

| Collection / article | URL | Type |
|---|---|---|
| **Getting Started** | `/{country}/adsupport/collections/getting-started` | Category |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 What Is the Seller Accelerator Program ⭐ featured | `/{country}/adsupport/articles/what-is-the-seller-accelerator-program` | Article |
| &nbsp;&nbsp;&nbsp;&nbsp;↳ **Overview** | `/{country}/adsupport/collections/overview` | Category |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📄 What Are Self-Serve Ad Campaigns | `/{country}/adsupport/articles/what-are-self-serve-ad-campaigns` | Article |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📄 How to Navigate the Campaigns Page | `/{country}/adsupport/articles/how-to-navigate-the-campaigns-page` | Article |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📄 How to Access Ad Manager | `/{country}/adsupport/articles/how-to-access-ad-manager` | Article |
| **Quick Guides** | `/{country}/adsupport/collections/quick-guides` | Category |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 How to Create a Product Ads Campaign | `/{country}/adsupport/articles/how-to-create-a-product-ads-campaign` | Article |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 How to Scale Your Ads with noon ads | `/{country}/adsupport/articles/how-to-scale-your-ads-with-noon-ads` | Article |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 How to Create a Brand Ads Campaign | `/{country}/adsupport/articles/how-to-create-a-brand-ads-campaign` | Article |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 How to Create a Display Ads Campaign | `/{country}/adsupport/articles/how-to-create-a-display-ads-campaign` | Article |
| **Release Notes** | `/{country}/adsupport/collections/release-notes` | Category |
| **Manage Campaigns** | `/{country}/adsupport/collections/manage-campaigns` | Category |
| **Optimize Campaigns** | `/{country}/adsupport/collections/optimize-campaigns` | Category |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 What Is Campaign Bidding | `/{country}/adsupport/articles/what-is-campaign-bidding` | Article |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 Campaign Targeting | `/{country}/adsupport/articles/campaign-targeting` | Article |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 Selecting SKUs to Advertise | `/{country}/adsupport/articles/selecting-skus-to-advertise` | Article |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 What is the Campaign Overview Report | `/{country}/adsupport/articles/what-is-the-campaign-overview-report` | Article |
| **Billing & Payment** | `/{country}/adsupport/collections/billing-payment` | Category |
| **Account Management** | `/{country}/adsupport/collections/account-management` | Category |
| **Advertising Resources** | `/{country}/adsupport/collections/advertising-resources` | Category |
| **FAQs** | `/{country}/adsupport/collections/faqs` | Category |

## 10. Seller Help Center

A help site for sellers. Admins manage it under **Content → Help Center**. Categories and articles can belong to one country or to all countries. Every URL has a country prefix; the version without it redirects.

| Page | URL | What it is |
|---|---|---|
| Help Center home | `/{country}/helpcenter` | Top-level categories, their sub-categories, and the featured article for that country. |
| Search | `/{country}/helpcenter/search?q=…` | Searches article titles, excerpts and bodies (EN and AR). Shows up to 30 results, most viewed first. |
| Category | `/{country}/helpcenter/category/{slug}` | A category with its sub-categories and published articles. |
| Article | `/{country}/helpcenter/article/{slug}` | The article with breadcrumbs and related articles. Opening it counts a view. |

### Current categories & articles (9 categories, 14 articles)

| Category / article | URL | Type · Country |
|---|---|---|
| **Getting Started** | `/{country}/helpcenter/category/getting-started` | Category · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 How to Create a Seller Account | `/{country}/helpcenter/article/how-to-create-a-seller-account` | Article · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 How to List Your First Product | `/{country}/helpcenter/article/how-to-list-your-first-product` | Article · All countries |
| **Fulfilled by noon (FBN)** | `/{country}/helpcenter/category/fulfilled-by-noon-fbn` | Category · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 كيفية حساب رسوم FBN الصادرة ورسوم إزالة المخزون في الإمارات | `/{country}/helpcenter/article/how-to-calculate-the-fbn-outbound-fee-and-the-inventory-removal-fee-in-uae` | Article · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 طرق استلام المرتجعات | `/{country}/helpcenter/article/receiving-methods-for-rtv-delivery` | Article · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 عملية تسليم المرتجعات الجزئية في مستودعات نون | `/{country}/helpcenter/article/partial-rtv-handover-in-noon-hubs` | Article · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;↳ **FBN Fees** | `/{country}/helpcenter/category/fbn-fees` | Category · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📄 رسوم الاستيفاء بواسطة نون (FBN) في الإمارات العربية المتحدة ⭐ featured | `/{country}/helpcenter/article/fulfilled-by-noon-fbn-fees-in-uae` | Article · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📄 دليل فهم مستويات البائعين وأداء الشحنات الواردة | `/{country}/helpcenter/article/understanding-seller-tiers-and-inbound-performance` | Article · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📄 تقرير الإرجاع إلى البائع (RTV) | `/{country}/helpcenter/article/return-to-vendor-rtv-report` | Article · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📄 كيف يمكنني تتبع شحنات المرتجعات إلى البائع (RTV) والإشعارات الخاصة بها؟ | `/{country}/helpcenter/article/how-can-i-track-my-rtv-shipments-and-notifications` | Article · All countries |
| **Orders & Shipping** | `/{country}/helpcenter/category/orders-shipping` | Category · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 How to Process an Order | `/{country}/helpcenter/article/how-to-process-an-order` | Article · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 How to Print a Shipping Label | `/{country}/helpcenter/article/how-to-print-a-shipping-label` | Article · All countries |
| **Returns** | `/{country}/helpcenter/category/returns` | Category · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 How Returns Work on noon | `/{country}/helpcenter/article/how-returns-work-on-noon` | Article · All countries |
| **Finance & Payments** | `/{country}/helpcenter/category/finance-and-payments` | Category · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 New Statement Detail Report | `/{country}/helpcenter/article/new-statement-detail-report` | Article · All countries |
| &nbsp;&nbsp;&nbsp;&nbsp;📄 Understanding Your Weekly Payout | `/{country}/helpcenter/article/understanding-your-weekly-payout` | Article · All countries |
| **Listings & Catalog** | `/{country}/helpcenter/category/listings-and-catalog` | Category · All countries |
| **Account Management** | `/{country}/helpcenter/category/account-management` | Category · All countries |
| **FAQs** | `/{country}/helpcenter/category/faqs` | Category · All countries |

## 11. Blog

Admins manage it under **Content → Blog** (posts and categories). Blog URLs have no country prefix; the country comes from the site domain.

| Page | URL | What it is |
|---|---|---|
| Blog list | `/blog` | The featured post, categories (only those with posts) and a post grid of 12 per page. Filters: `?category={slug}`, `?tag={tag}`, `?search={text}`. |
| Blog post | `/blog/{slug}` | The full post and 3 related posts from the same category. |

Action: `POST /blog/posts/{post}/increment-views` (counts a view).

### Blog categories (4)

| Category | Filter URL | Parent | Status |
|---|---|---|---|
| Platform News | `/blog?category=platform-news` | — | Active |
| Vendor Tips | `/blog?category=vendor-tips` | — | Active |
| Seller Stories | `/blog?category=seller-stories` | — | Active |
| E-Commerce Insights | `/blog?category=ecommerce-insights` | — | Active |

### Blog posts (12)

| Post | URL | Category | Country | Status | Published |
|---|---|---|---|---|---|
| Introducing Multi-Country Storefronts | `/blog/introducing-multi-country-storefronts` | Platform News | All countries | published | 2026-10-05 |
| New Vendor Dashboard Analytics — Know Your Numbers | `/blog/new-vendor-dashboard-analytics` | Platform News | All countries | published | 2026-09-27 |
| 5 Pricing Strategies Every Marketplace Vendor Should Know | `/blog/pricing-strategies-for-marketplace-vendors` | Vendor Tips | All countries | published | 2026-09-27 |
| How Amira Grew Her Abaya Brand 300% in One Year | `/blog/how-amira-grew-her-abaya-brand-300-percent` | Seller Stories | All countries | published | 2026-09-23 |
| Building a Food Brand from a Home Kitchen | `/blog/building-a-food-brand-from-a-home-kitchen` | Seller Stories | All countries | published | 2026-09-22 |
| Scheduled Maintenance — July 2026 | `/blog/platform-scheduled-maintenance-july` | Platform News | All countries | published | 2026-09-17 |
| How to Write Product Titles That Actually Convert | `/blog/how-to-write-product-titles-that-convert` | Vendor Tips | All countries | published | 2026-09-15 |
| From Garage to Fulfilment Centre: Karim's Electronics Journey | `/blog/from-garage-to-fulfillment-center-karim-story` | Seller Stories | All countries | published | 2026-09-13 |
| The Ramadan Commerce Playbook for 2026 | `/blog/ramadan-commerce-playbook-2026` | E-Commerce Insights | All countries | published | 2026-09-10 |
| Optimising Product Images for Higher Conversions | `/blog/optimising-product-images-for-conversions` | Vendor Tips | All countries | published | 2026-09-09 |
| Mobile Commerce Trends in the Gulf — What the Data Says for 2026 | `/blog/mobile-commerce-trends-gulf-2026` | E-Commerce Insights | All countries | published | 2026-09-09 |
| The Rise of Social Commerce in the Region | `/blog/the-rise-of-social-commerce-in-the-region` | E-Commerce Insights | All countries | published | 2026-09-06 |

Only posts with status **published** are public; the rest return 404.

## 12. Static content pages

URL pattern: `/pages/{slug}`. Admins manage these under **Content → Static Pages**. Only pages whose audience is `portal` or `both` appear here. A page set for a specific country takes priority over the global one.

| Page | URL | Audience | Country | Footer group |
|---|---|---|---|---|
| About Us | `/pages/about-us` | both | All countries | company |
| Terms & Conditions | `/pages/terms-and-conditions` | both | All countries | policies |
| Contact Us | `/pages/contact-us` | both | All countries | help |
| Privacy Policy | `/pages/privacy-policy` | both | All countries | policies |
| Return Policy | `/pages/return-policy` | both | All countries | policies |
| Shipping Policy | `/pages/shipping-policy` | both | All countries | policies |
| Refund Policy | `/pages/refund-policy` | both | All countries | policies |

## 13. Legal — Seller Agreement

| Page | URL | What it is |
|---|---|---|
| Seller agreement | `/legal/seller-agreement` | The public seller terms for the selected country (`?country=`). |
| Agreement file | `/legal/seller-agreement/{template}/{ar|en}` | Downloads or streams the agreement document in Arabic or English. |

## 14. Language & country switchers

| URL | What it does |
|---|---|
| `/language/{ar|en}` | Saves the language in the session and returns to the previous page. |
| `/country/{country}` | Saves the country in the session. On country-prefixed pages it reloads the same page for the new country. |

## 15. Behind-the-scenes endpoints (not pages)

These are the form and AJAX endpoints already listed in the sections above, grouped here for reference:

| Method | URL | Used by |
|---|---|---|
| POST | /join/select | Join page |
| GET | /join/options | Join page (AJAX) |
| POST | /join/continue | Join page |
| POST | /vendors/register/step/{1-3} | Vendor wizard |
| GET | /vendors/register/check-slug | Vendor wizard |
| GET | /vendors/register/cities | Vendor wizard |
| GET | /vendors/register/document-requirements | Vendor wizard |
| POST | /vendors/register/upload | Vendor wizard |
| DELETE | /vendors/register/document | Vendor wizard |
| POST | /vendors/register/complete | Vendor wizard |
| POST | /vendors/register/track | Vendor wizard (analytics) |
| POST | /companies/travel-agency/register/step/{1-2} | Travel wizard |
| DELETE | /companies/travel-agency/register/logo | Travel wizard |
| POST | /companies/travel-agency/register/complete | Travel wizard |
| POST | /{country}/advertise/request (and /advertise/request) | Ads request form |
| POST | /blog/posts/{post}/increment-views | Blog post |
