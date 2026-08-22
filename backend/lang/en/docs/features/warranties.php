<?php

return [
    'title' => 'Warranties',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => 'What It Is',
        'body' => "Two separate warranty systems: the vendor's standard warranty bundled with a product, and platform-sold extended warranty plans purchased at checkout.",
    ],

    'standard' => [
        'heading' => '1. Standard Warranty',
        'provided_by' => 'Provided by the vendor as part of the product listing; duration is',
        'on_product' => 'on the product',
        'claims_by_prefix' => 'Claims are filed by the',
        'customer' => 'customer',
        'claims_by_suffix' => '&mdash; never by the vendor or admin',
        'vendors_prefix' => 'Vendors',
        'cannot' => 'cannot',
        'vendors_suffix' => 'see claims on admin-listing items (403 guard)',
    ],

    'extended' => [
        'heading' => '2. Extended Warranty Plans',
        'admin_creates' => 'Admin creates plans at',
        'scoped' => 'scoped to parent product categories',
        'displayed' => 'Displayed at listing detail, purchasable at checkout',
        'payment_goes' => 'Payment goes to the platform, not the vendor',
        'after_delivery' => 'After delivery',
    ],

    'claim_flow' => [
        'heading' => '3. Warranty Claim Flow',
        'resolution_prefix' => 'Resolution types:',
        'repair' => 'repair',
        'replace' => 'replace',
        'refund' => 'refund',
        'message_thread' => 'Message thread between customer, vendor, and admin',
    ],

    'admin_view' => [
        'heading' => '4. Admin View',
        'claims' => 'all claims, filterable by status/vendor',
        'purchases' => 'all extended warranty sales (revenue report)',
    ],

    'rules' => [
        'heading' => 'Who Uses It & Key Rules',
        'customers_label' => 'Customers',
        'customers' => 'file claims and purchase extended plans',
        'vendors_label' => 'Vendors',
        'vendors' => 'only see claims for their own listings, never admin-managed listings',
        'admin_label' => 'Admin',
        'admin' => 'arbitrates claims, manages plans, and tracks extended-warranty revenue',
    ],
];
