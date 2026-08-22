<?php

return [
    'title' => 'System Pages',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => 'What It Is',
        'body_prefix' => "Everything in the admin sidebar's",
        'system_group' => 'System',
        'body_suffix' => 'group: platform-wide geography, currencies, document requirements, payments infrastructure, warehouse operations, and the audit trail.',
    ],

    'countries' => [
        'heading' => 'Countries',
        'launching' => 'Launching a country enables it on the platform for customers and vendors',
        'per_country' => 'Per-country: payment methods, shipping settings, category overrides, VAT rate',
        'deactivating' => 'Deactivating a country hides it from the storefront; existing orders are unaffected',
    ],

    'cities' => [
        'heading' => 'Cities',
        'body' => 'manage cities within each country. Bulk import via CSV. Cities are assigned to shipping zones for rate calculation.',
    ],

    'currencies' => [
        'heading' => 'Currencies',
        'body' => 'exchange rates for all supported currencies',
        'override' => 'Manual rate override or auto-refresh from an exchange rate API',
        'bigint_note' => 'All monetary values are stored as BIGINT base units &mdash; the displayed rate is for display conversion only.',
    ],

    'shipping_zones' => [
        'heading' => 'Shipping Zones & Shipping Methods',
        'see' => 'See the',
        'link' => 'Shipping',
        'documentation' => 'documentation.',
    ],

    'document_types' => [
        'heading' => 'Document Types',
        'body' => 'configures which documents vendors must upload during onboarding, per country, with a required/optional toggle per document type.',
    ],

    'payment_methods' => [
        'heading' => 'Payment Methods',
        'body' => 'enable/disable payment methods per country. Sort order affects checkout display.',
    ],

    'payment_gateways' => [
        'heading' => 'Payment Gateways',
        'body' => 'configure gateway credentials',
        'etc' => 'etc.',
        'strategy' => 'using the Strategy Pattern.',
        'test_connection' => 'Test connection before going live',
        'webhook_logs' => 'Webhook logs per gateway for debugging',
    ],

    'weight_slabs' => [
        'heading' => 'Weight Slabs',
        'body' => 'Shipping surcharge tiers by weight',
        'see' => 'see the',
        'link' => 'Shipping',
        'docs' => 'docs.',
    ],

    'subsidies' => [
        'heading' => 'Shipping Subsidies',
        'body' => 'Exceptional zone cost split rules',
        'see' => 'see the',
        'link' => 'Subsidy',
        'docs' => 'docs.',
    ],

    'warehouses' => [
        'heading' => 'Warehouses',
        'see' => 'See the',
        'link' => 'Warehouses',
        'documentation' => 'documentation.',
    ],

    'transfers' => [
        'heading' => 'Inventory Transfers',
        'body' => 'move stock between warehouses.',
    ],

    'surcharges' => [
        'heading' => 'Shipping Surcharges',
        'body' => 'extra fees on FBN outbound shipments.',
    ],

    'activity_log' => [
        'heading' => 'Activity Log',
        'body' => 'immutable audit trail of all admin actions.',
        'filter' => 'Filter by: causer (which admin), event type, date range',
        'shows' => 'Shows: what changed, old value, new value, timestamp',
    ],

    'rules' => [
        'heading' => 'Who Uses It & Key Rules',
        'admin_only' => 'Admin only',
        'not_exposed' => 'none of these pages are exposed to vendors, customers, or other panels',
        'no_retroactive' => 'Deactivating a country or currency never retroactively changes historical orders',
        'immutable' => 'The activity log is immutable &mdash; entries cannot be edited or deleted, only read and filtered',
    ],
];
