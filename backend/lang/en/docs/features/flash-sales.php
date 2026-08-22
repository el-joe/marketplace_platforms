<?php

return [
    'title' => 'Flash Sales',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => '1. What It Is',
        'p1' => 'Time-limited sale events where vendors submit discounted prices for their listings. Platform controls the event; vendors participate by submitting prices below a threshold.',
    ],

    'lifecycle' => [
        'heading' => '2. Lifecycle',
        'p1' => 'Admin: create event → set start/end times → invite vendors → review submissions → publish.',
    ],

    'vendor_flow' => [
        'heading' => '3. Vendor Participation Flow',
        'step1' => 'Vendor receives invitation',
        'step1_note' => 'note double-ti intentional',
        'step2' => 'Vendor views event in partner panel',
        'step3' => 'Vendor submits listing price for the event (must be below original price)',
        'step4' => 'Admin reviews submission → approve or reject',
        'step5' => 'At event start: approved listings shown at discounted price',
        'step6' => 'At event end: prices revert automatically',
    ],

    'admin_tools' => [
        'heading' => '4. Admin Tools',
        'stats_label' => 'Submission stats',
        'stats_desc' => 'how many submitted vs total invited',
        'monitor_label' => 'Live monitor',
        'monitor_desc' => 'real-time sales data during active event',
        'analytics_label' => 'Analytics',
        'analytics_desc' => 'post-event performance',
        'bulk_label' => 'Bulk review',
        'bulk_desc' => 'approve/reject multiple submissions at once',
        'history_label' => 'Price history',
        'history_desc' => 'track price changes per listing across events',
    ],
];
