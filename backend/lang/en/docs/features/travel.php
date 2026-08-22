<?php

return [
    'title' => 'Travel Packages',
    'breadcrumb' => 'Features',

    'overview' => [
        'heading' => '1. Overview',
        'body' => 'Agencies create travel packages on the travel agency panel. Admin reviews and approves packages. Customers book via',
    ],

    'package_flow' => [
        'heading' => '2. Package Flow',
        'step1' => 'Agency creates &rarr; submits for review',
        'step2' => 'Admin approves | rejects',
        'step3' => 'Live: customer can inquire or book',
        'step4' => 'Agency confirms booking &rarr; travel completed',
    ],

    'booking_statuses' => [
        'heading' => '3. Booking Statuses',
    ],

    'admin_controls' => [
        'heading' => '4. Admin Controls',
        'agencies' => 'approve, suspend, reactivate travel agencies',
        'packages' => 'review queue &mdash; approve, reject, expire; contract download',
        'bookings' => 'all bookings platform-wide',
        'catalog' => 'catalog management for destinations',
        'change_requests' => 'agency change requests for locked profile sections',
    ],

    'rules' => [
        'heading' => 'Who Uses It & Key Rules',
        'agencies_label' => 'Travel agencies',
        'agencies_create' => 'create and manage packages;',
        'admin_label' => 'admin',
        'sole_approver' => 'is the sole approver of both agencies and packages',
        'locked_sections' => 'Locked profile sections can only change through the change-request approval flow, never edited directly by the agency',
        'booking_completed' => 'A booking cannot move to',
        'without_first' => 'without first passing through',
    ],
];
