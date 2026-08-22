<?php

return [
    'title' => 'Admin Panel',

    'dashboard' => [
        'title' => 'Dashboard',
        'purpose' => 'Purpose: Real-time overview of platform health.',
        'main' => 'Main dashboard with revenue chart, order counts, top sellers, pending items',
        'analytics' => 'Detailed analytics: revenue by period, top products/vendors/categories, customer stats, SLA metrics, flash sale analytics, return rates, support metrics',
    ],

    'catalog' => [
        'title' => 'Catalog Management',
        'products' => 'Create/edit products with variants, images, GTIN, country settings',
        'categories' => 'Nested category tree with commissions, attributes, shipping methods',
        'brands' => 'Brand CRUD with logo management',
        'attributes' => 'Product attribute and value management (used for variant filtering)',
        'highlights' => 'Pin products to featured spots per category',
        'bestsellers' => 'Auto-computed from total_sold on vendor_listings (read-only)',
    ],

    'vendors' => [
        'title' => 'Vendor Management',
        'applications' => 'Review queue for new vendor applications (start-review, assign-me, approve/reject)',
        'vendors' => 'All active vendors: suspend, blacklist, assign account manager, lock/unlock sections, issue strikes, manage hold',
        'change_requests' => 'Pending changes to locked vendor sections (bank accounts etc.)',
    ],

    'orders' => [
        'title' => 'Orders',
        'all' => 'All platform orders with filters; view/update status; force-cancel; refund; flag fraud',
        'actions_intro' => 'Order actions available per status:',
        'confirmed_processing' => 'confirmed &rarr; processing: assign shipping',
        'processing_shipped' => 'processing &rarr; shipped: mark shipped',
        'shipped_delivered' => 'shipped &rarr; delivered: mark delivered',
        'any_cancelled' => 'any &rarr; cancelled: force-cancel (admin only)',
        'any_refund' => 'any &rarr; refund: process refund (auth required)',
        'sub_order_note' => 'Sub-order status transitions available via',
    ],

    'finance' => [
        'title' => 'Finance',
        'payouts' => 'Vendor payout batches: approve/process/hold/recalculate',
        'transactions' => 'All payment transactions; refund management sub-section',
        'ledger' => 'Double-entry ledger with transaction groups',
        'cod_settlements' => 'COD cash collection settlements from delivery agents',
        'financial_reports' => 'Revenue reports with export',
        'warranty_purchases' => 'Extended warranty sales report',
        'wallets' => 'Customer wallets: adjust, freeze, withdrawal approvals',
        'subscriptions' => 'Vendor subscription plans + active subscriptions + invoices',
        'subscription_plans' => 'Plan CRUD (monthly/annual pricing)',
        'fbn_storage_fees' => 'FBN monthly storage fee invoices per vendor',
        'fbn_inbound' => 'Incoming inventory from vendors to platform warehouses',
        'fbn_marketplace' => 'FBN marketplace shipping rules',
        'cod_lifecycle' => 'COD Settlement lifecycle: Agent collects cash &rarr; Agent remits to platform &rarr; Admin generates settlement &rarr; Admin marks settled &rarr; vendor_payout unlocked for that sub_order',
    ],

    'customers' => [
        'title' => 'Customers',
        'customers' => 'Customer list: suspend/ban/reactivate, adjust loyalty points, export data, send notifications, regenerate QR, revoke devices',
    ],

    'marketing' => [
        'title' => 'Marketing (Ad System)',
        'banners' => 'Promotional banners with placement slots and scheduling',
        'ad_campaigns' => 'Self-serve ad campaigns by vendors: approve/reject/pause/resume + fraud alerts',
        'ad_slots' => 'Named placement slots where ads appear (home hero, category top, etc.)',
        'paid_ad_bookings' => 'Reserved ad placements booked by vendors for fixed dates',
        'flash_sales' => 'Time-limited sale events: create, invite vendors, review submissions, live monitor',
        'vendor_campaign_offers' => 'Vendor-created marketer campaigns: approve/reject',
        'secret_promotions' => 'Platform-exclusive discount promotions visible only to specific marketers',
    ],

    'marketers' => [
        'title' => 'Marketers',
        'marketers' => 'All marketers: approve, reject, suspend, view campaigns/conversions/payouts',
        'marketer_campaigns' => 'Marketer-initiated campaigns: approve/reject/pause',
        'marketer_conversions' => 'Sales attributed to marketers; bulk approve',
        'marketer_payouts' => 'Generate + approve + process marketer commission payouts',
        'marketer_samples' => 'Product sample requests by marketers: approve/dispatch/reject',
        'affiliate_codes' => 'Promo codes assigned to affiliate marketers',
        'influencer_deals' => 'Flat-fee content deals: propose, approve, manage deliverables, initiate payment',
    ],

    'delivery' => [
        'title' => 'Delivery',
        'agents' => 'Create/manage delivery agents, verify documents, assign zones',
        'zones' => 'Define delivery zones; assign agents; view live agent map',
        'assignments' => 'View/manage deliveries; auto-assign or manual-assign; live map',
        'payouts' => 'Generate/approve/process delivery agent payouts',
    ],

    'fulfillment' => [
        'title' => 'Fulfillment / Warehouses',
        'warehouses' => 'Platform FBN + vendor-owned warehouses; inventory; adjustments; overage fees; vendor storage limits; inventory transfers',
    ],

    'shipping' => [
        'title' => 'Shipping System',
        'zones' => 'Zone CRUD; assign cities; manage per-zone shipping rates',
        'methods' => 'Method + carrier + rate management; country settings',
        'weight_slabs' => 'Extra fee tiers by weight range',
        'subsidies' => 'Platform subsidy rules for exceptional zones',
        'warehouse_surcharges' => 'FBN warehouse surcharges on outbound shipments',
        'companies' => 'Carrier company management; supervisor notifications; fallback rules',
        'carrier_claims' => 'Claims against carriers for lost/damaged shipments',
        'carrier_scorecard' => 'Performance scores per carrier; trend data',
    ],

    'support' => [
        'title' => 'Support',
        'tickets' => 'Customer support tickets: reply, assign, update status/priority',
        'disputes' => 'Order disputes: reply, assign, resolve',
        'warranty_claims' => 'Product warranty claims: review, resolve, message thread',
    ],

    'travel' => [
        'title' => 'Travel',
        'agencies' => 'Agency approval/suspension/reactivation',
        'packages' => 'Package review queue: approve/reject/expire; contract download',
        'bookings' => 'All travel bookings overview',
        'countries' => 'Travel destination countries',
        'cities' => 'Travel destination cities',
        'inclusions' => 'Standard inclusions (meals, transfers, etc.)',
        'inquiries' => 'Public inquiries from storefront',
        'change_requests' => 'Agency change request approval',
    ],

    'classifieds' => [
        'title' => 'Classifieds',
        'categories' => 'Classified ad categories',
        'contract_templates' => 'Legal contract templates for classified listings',
        'listings' => 'Review queue: approve/reject; verify attachments',
    ],

    'content' => [
        'title' => 'Content',
        'page_builder' => 'Visual homepage/page builder (see Page Builder docs)',
        'app_contexts' => 'App navigation contexts (Main, Super Mall, Food, 15min, etc.)',
        'reviews' => 'Product reviews moderation',
        'blog_categories' => 'Blog category CRUD',
        'blog_posts' => 'Blog post management with attachments',
        'adsupport_collections' => 'Knowledge hub collection groups',
        'adsupport_articles' => 'Knowledge hub articles for vendor help center',
        'helpcenter_categories' => 'Customer-facing help center categories',
        'helpcenter_articles' => 'Customer-facing help center articles',
        'faqs' => 'Platform FAQs',
        'portal_content' => 'Static page content editor (About, Terms, Privacy, etc.)',
        'content_settings' => 'All portal media/text settings (logo, banners, colors, etc.)',
        'radio' => 'Radio channel + slot scheduling',
    ],

    'system' => [
        'title' => 'System',
        'countries' => 'Platform countries (launch, deactivate, payment methods, shipping settings)',
        'cities' => 'City management with bulk import',
        'currencies' => 'Currency rates and display settings',
        'shipping_zones_note' => '(Also under Shipping section)',
        'shipping_methods_note' => '(Also under Shipping section)',
        'vendor_document_types' => 'Required document types for vendor onboarding',
        'payment_methods' => 'Enabled payment methods per country',
        'payment_gateways' => 'Gateway credential configuration',
        'weight_slabs_note' => 'Weight surcharge slabs',
        'shipping_subsidies_note' => 'Exceptional zone subsidy rules',
        'warehouses_note' => '(Also under Warehouses section)',
        'settings' => 'Platform-wide settings grouped by category',
        'activity_log' => 'Audit trail of all admin actions',
    ],

    'administration' => [
        'title' => 'Administration',
        'admins' => 'Admin user CRUD; roles; impersonation; session management; reset password',
        'roles' => 'Admin role and permission management',
    ],
];
