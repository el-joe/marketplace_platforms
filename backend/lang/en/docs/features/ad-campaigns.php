<?php

return [
    'title' => 'Ad Campaigns (Self-Serve)',

    'what_it_is' => [
        'heading' => '1. What It Is',
        'body' => "Vendors create their own advertising campaigns to promote their listings within the platform's native ad network. Admin reviews and approves each campaign.",
    ],

    'how_it_works' => [
        'heading' => '2. How It Works',
        'create' => 'Vendor creates campaign (from partner panel) with: listing, budget, bid, dates',
        'submitted' => 'Campaign submitted → status: pending',
        'review' => 'Admin reviews at /admin/ad-campaigns → approve or reject',
        'live' => 'Live: ad shown in ad slots across storefront',
        'pause' => 'Admin can pause/resume live campaigns',
        'exhausted' => 'Budget exhausted → campaign auto-pauses',
    ],

    'ad_slots' => [
        'heading' => '3. Ad Slots',
        'body1' => 'Named positions where ads can appear. Each slot: name, placement context, dimensions, max concurrent bookings.',
        'body2' => 'Slots link to Paid Ad Bookings for reserved (non-auction) placements.',
    ],

    'paid_bookings' => [
        'heading' => '4. Paid Ad Bookings',
        'body1' => 'Vendors can reserve specific ad slots for specific date ranges at a fixed price.',
        'body2' => 'Review queue. Admin reviews creative (image/video) before approving.',
        'status' => 'Status:',
    ],

    'fraud_alerts' => [
        'heading' => '5. Fraud Alerts',
        'body' => 'Suspicious click patterns detected by the system. Admin can block fraud patterns to protect advertiser budgets.',
    ],
];
