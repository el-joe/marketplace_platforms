<?php

return [
    'title' => 'Partner (Vendor) Panel',
    'meta' => 'URL: <code>partner.{domain}</code> &middot; Access: <code>vendor_admins</code> table &middot; Guard: <code>vendor</code>',

    'auth' => [
        'title' => 'Authentication',
        'login' => 'Login at',
        'approval' => 'Vendor account must be approved and active — suspended vendors see',
        'suspended_page' => 'page',

        'password_reset' => 'Password reset via email',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'summary' => 'Sales summary, orders chart, pending actions, low stock alerts, recent orders',
    ],

    'orders' => [
        'title' => 'Orders',
        'orders' => 'Sub-orders assigned to this vendor',
        'actions' => 'Actions: confirm &rarr; ship &rarr; out-for-delivery &rarr; deliver | cancel',
        'scope' => 'Vendor sees ONLY their sub_orders — never other vendors\' data',
    ],

    'listings' => [
        'title' => 'Listings',
        'listings' => "Vendor's product listings on the marketplace",
        'create' => 'Create: search existing product catalog &rarr; set price, fulfillment model, warehouse',
        'edit' => 'Edit: update price, shipping, dimensions, adjust stock, toggle status',
        'shipping_preview' => 'Shipping preview: see how shipping fee is calculated for a specific listing',
    ],

    'inventory' => [
        'title' => 'Inventory',
        'inventory' => 'Stock levels across all listings',
        'filters' => 'Low stock and out-of-stock filtered views',
        'movement' => 'Movement history per listing (inbound, outbound, adjustments)',
    ],

    'payouts' => [
        'title' => 'Payouts',
        'payouts' => 'Payout batches from platform settlements',
        'summary' => 'Earnings summary: total earned, pending, commission deducted',
    ],

    'coupons' => [
        'title' => 'Coupons',
        'coupons' => 'Vendor-specific coupons applied only to their sub_orders',
    ],

    'flash_sales' => [
        'title' => 'Flash Sales',
        'events' => 'Platform flash sale events the vendor is invited to',
        'submit' => 'Submit listing prices for flash sale inclusion',
        'stats' => 'Live stats during active sales',
    ],

    'bank_accounts' => [
        'title' => 'Bank Accounts',
        'accounts' => "Vendor's bank accounts for payout transfer",
        'set_primary' => 'Set primary, delete (not editable — change request required for locked section)',
    ],

    'fbn' => [
        'title' => 'FBN (Fulfilled by Platform)',
        'inbound' => 'Ship inventory to platform warehouses',
        'storage_fees' => 'View storage fee charges per listing',
        'warehouses' => 'View assigned platform warehouses',
    ],

    'performance' => [
        'title' => 'Performance',
        'scorecard' => 'Vendor scorecard: order completion rate, cancellation rate, reviews, SLA',
    ],

    'exceptional_zones' => [
        'title' => 'Exceptional Shipping Zones',
        'opt_in' => 'Opt into serving remote zones with delivery subsidy applied',
    ],

    'team' => [
        'title' => 'Team Management',
        'team' => 'Manage staff with role-based access',
        'roles' => 'Create custom permission roles scoped to this vendor',
    ],

    'profile' => [
        'title' => 'Profile &amp; Settings',
        'profile' => 'Agency info, logo, contact',
        'bank_accounts' => 'Bank account management',
        'change_requests' => 'View pending change requests for locked sections',
        'warehouse' => 'Register/manage vendor-owned warehouses',
    ],

    'packaging' => [
        'title' => 'Packaging Supplies',
        'catalog' => 'Browse platform packaging items',
        'order' => 'Place packaging order (vendor bears delivery fee)',
        'requests' => 'View own packaging order history',
    ],
];
