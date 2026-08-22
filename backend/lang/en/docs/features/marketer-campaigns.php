<?php

return [
    'title' => 'Marketer Campaigns',

    'what_it_is' => [
        'heading' => '1. What It Is',
        'body' => 'Marketers (affiliates or influencers) create campaigns to promote platform products, classifieds, or travel packages and earn commissions on resulting sales.',
    ],

    'commission_types' => [
        'heading' => '2. Commission Types',
        'percentage' => 'rounded down',
        'flat_per_conversion' => 'per completed order',
        'flat_per_click' => 'paid per click (rare, usually for influencers)',
    ],

    'attribution' => [
        'heading' => '3. Attribution Models',
        'last_click' => 'last marketer link clicked before purchase gets 100% credit',
        'first_click' => 'first marketer link clicked in session gets 100% credit',
        'linear' => 'credit split equally among all marketer touchpoints (remainder to last)',
    ],

    'conversion_lifecycle' => [
        'heading' => '4. Conversion Lifecycle',
        'placed' => "Order placed via marketer link → conversion.status = 'pending'",
        'approved' => "Return window passes → conversion.status = 'approved'",
        'payout' => 'sub_order completed → marketer payout generated',
        'reversed' => "If order refunded → conversion.status = 'reversed', earnings decremented",
    ],

    'budget_guard' => [
        'heading' => '5. Budget Exhaustion Guard',
        'body' => 'marketer_campaigns.budget (if set) checked with lockForUpdate() before each conversion.',
        'note' => 'If budget exhausted → campaign pauses automatically, no new conversions recorded.',
    ],

    'campaign_types' => [
        'heading' => '6. Campaign Types Available',
        'product_label' => 'product campaigns:',
        'product' => 'promote specific vendor_listings',
        'classified_label' => 'classified campaigns:',
        'classified' => 'promote classified ad listings',
        'travel_label' => 'travel campaigns:',
        'travel' => 'promote travel packages',
    ],

    'admin_actions' => [
        'heading' => '7. Admin Actions at /admin/marketer-campaigns',
        'approve_label' => 'approve:',
        'approve' => 'campaign goes live',
        'reject_label' => 'reject:',
        'reject' => 'with reason (marketer can edit and resubmit)',
        'approve_pause_label' => 'approve pause request:',
        'approve_pause' => 'when active marketer requests pause',
        'dismiss_pause_label' => 'dismiss pause request:',
        'dismiss_pause' => 'if request is not valid',
    ],

    'payout_calc' => [
        'heading' => '8. Payout Calculation',
        'gross' => 'sum of approved conversions in period',
        'net' => 'gross - platform_share (if applicable) - tax',
        'grouping' => 'Payouts grouped by currency — never summed across currencies.',
    ],
];
