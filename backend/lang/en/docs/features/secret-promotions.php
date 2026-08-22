<?php

return [
    'title' => 'Secret Promotions',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => '1. What It Is',
        'p1' => "Exclusive, limited-availability promotions that are hidden from the general public. Only accessible via a marketer's special tracking link or a specific promo code.",
        'p2' => 'Purpose: reward loyal customers or influencer audiences with prices not shown on the listing.',
    ],

    'security_rule' => [
        'heading' => '2. Key Security Rule',
        'and' => 'and',
        'p1' => 'are NEVER exposed in any customer-facing or non-admin API response or Blade view. These are admin-only fields.',
    ],

    'how_it_works' => [
        'heading' => '3. How It Works',
        'step1' => 'Admin creates secret promotion at',
        'step1_fields' => 'select vendor + listing, set discounted price, assign marketer(s), set start/end dates, set max redemptions',
        'step2' => 'Linked to specific marketer(s) — only their audience can see the price',
        'step3' => "Marketer shares their tracking link → customer lands on listing → sees secret price",
        'step4' => 'Purchase tracked back to marketer as conversion',
    ],

    'statuses' => [
        'heading' => '4. Statuses',
        'p1' => 'Admin can: approve, reject (with reason), expire manually, duplicate.',
    ],

    'marketer_view' => [
        'heading' => '5. Marketer View',
        'p1' => 'Marketer sees their secret promotions at',
        'p2' => 'They can use their tracking link to share the promotion.',
        'p3' => 'They earn commission on each redemption (same as campaign conversions).',
    ],
];
