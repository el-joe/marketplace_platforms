<?php

return [
    'title' => 'Shipping Subsidy',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => 'What It Is',
        'p1' => 'A cost-sharing mechanism that lets vendors offer free delivery in far-flung "exceptional" shipping zones, with the platform absorbing part of the cost. See the',
        'shipping_link' => 'Shipping & Zones',
        'p2' => 'doc for the underlying zone/rate mechanism.',
    ],

    'how_it_works' => [
        'heading' => 'How It Works',
        'step1' => 'Admin defines exceptional zones at',
        'step2' => 'Admin sets subsidy rules at',
        'step2_suffix' => 'per zone + country',
        'step3' => 'Vendor opts in at',
        'step4' => 'At checkout, the customer sees free delivery',
        'step5_a' => "is deducted from the vendor's payout;",
        'step5_b' => "is shown as a cost in the platform P&L",
    ],

    'who_uses_it' => [
        'heading' => 'Who Uses It',
        'admin_label' => 'Admin',
        'admin_desc' => 'defines zones and sets the vendor/platform cost split',
        'vendor_label' => 'Vendor',
        'vendor_desc' => 'opts in per zone from the partner panel — opt-in is voluntary, never forced',
        'customer_label' => 'Customer',
        'customer_desc' => 'only ever sees "free delivery," never the underlying split',
    ],

    'key_rules' => [
        'heading' => 'Key Rules & Invariants',
        'rule1' => 'Subsidy rules are always scoped to a zone + country pair — there is no global default split',
        'rule2' => 'A vendor not opted in to a zone pays the normal shipping fee for orders shipping there (no subsidy applied)',
        'rule3' => 'is a real platform cost and reflected in financial reports, not just a display trick',
    ],
];
