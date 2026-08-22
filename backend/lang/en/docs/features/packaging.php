<?php

return [
    'title' => 'Packaging Supplies',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => 'What It Is',
        'p1' => 'The platform sells its own branded packaging materials to vendors — boxes, bags, tape, labels, etc. Vendors order supplies from the platform and bear the delivery fee themselves; it is never subsidized.',
    ],

    'admin_side' => [
        'heading' => 'Admin Side',
        'catalog_label' => 'Catalog',
        'catalog_desc' => 'add/edit/toggle supply items (name, type, size, unit price, stock)',
        'orders_label' => 'Orders',
        'orders_desc' => 'review pending vendor packaging orders → approve/reject/ship/deliver',
    ],

    'vendor_side' => [
        'heading' => 'Vendor Side',
        'catalog_label' => 'Catalog',
        'catalog_desc' => 'browse available items with the delivery fee shown for their country',
        'cart_label' => 'Alpine.js cart',
        'cart_desc' => 'add items with quantities, see running total + delivery fee + grand total',
        'order_flow' => 'Place order → platform notified → status',
    ],

    'delivery_fee' => [
        'heading' => 'Delivery Fee',
        'p1' => 'Comes from settings key',
        'p2' => 'BIGINT, base currency',
        'p3' => 'Set per country in',
    ],

    'stock_snapshot' => [
        'heading' => 'Stock Snapshot',
        'p1' => 'is snapshotted at order time and stored on',
        'p2' => 'Price changes made after an order is placed do not affect that order.',
    ],

    'who_rules' => [
        'heading' => 'Who Uses It & Key Rules',
        'admin_label' => 'Admin',
        'admin_desc' => 'owns the catalog and fulfills orders',
        'vendors_label' => 'Vendors',
        'vendors_desc' => 'browse, order, and pay the delivery fee — the fee is never waived or subsidized',
        'rule3' => 'Historical orders are immune to later price changes because of the unit-cost snapshot',
    ],
];
