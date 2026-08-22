<?php

return [
    'title' => 'Classifieds Marketplace',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => '1. What It Is',
        'body' => 'A classified listings marketplace (like Property Finder or Dubizzle) embedded in the platform. Customers and vendors can list items &mdash; real estate, vehicles, services, etc. &mdash; for sale or rent.',
    ],

    'how_it_works' => [
        'heading' => '2. How It Works',
        'step1' => 'Customer creates a listing &rarr; signs a digital contract &rarr; listing submitted for review',
        'step2' => 'Admin reviews &rarr; approve | reject',
        'step3' => 'Approved listing visible at',
    ],

    'admin_management' => [
        'heading' => '3. Admin Management',
        'categories' => 'classified listing categories (rentals, sales, services)',
        'contract_templates' => 'legal contracts per category',
        'listings' => 'review queue with approve/reject + attachment verification',
    ],

    'map_view' => [
        'heading' => '4. Map View',
        'body' => 'Classifieds have lat/lng; the storefront shows a map view of all active listings. The',
        'endpoint' => 'endpoint serves GeoJSON for the map.',
    ],

    'inquiries' => [
        'heading' => '5. Inquiries',
        'body' => 'Customers can inquire on a listing without logging in (throttled: 5/min). The seller is notified and responds privately.',
    ],

    'rules' => [
        'heading' => 'Who Uses It & Key Rules',
        'customers_vendors_label' => 'Customers and vendors',
        'both_create' => 'both create listings;',
        'admin_label' => 'admin',
        'sole_approver' => 'is the sole approver',
        'never_visible' => 'A listing is never publicly visible until it is admin-approved',
        'contract_before' => 'Digital contract signing happens before submission, not after approval',
    ],
];
