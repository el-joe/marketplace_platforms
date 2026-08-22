<?php

return [
    'title' => 'Vendor Campaigns (Marketer Invitations)',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => '1. What It Is',
        'p1' => 'Vendors invite marketers to promote their specific products in exchange for a commission per conversion.',
        'p2' => "This is distinct from self-serve ads — it's a performance-based partnership between vendor and marketer.",
    ],

    'how_it_works' => [
        'heading' => '2. How It Works',
        'step1' => 'Vendor creates campaign offer (partner panel → Campaigns)',
        'step2' => 'Vendor invites specific marketers or all eligible marketers',
        'step3' => 'Marketer receives invitation → accepts or declines',
        'step4' => "Accepted marketer promotes the vendor's products using tracking links/codes",
        'step5' => "When a customer converts via the marketer's link → conversion recorded",
        'step6' => 'Marketer earns commission; deducted from vendor payout at settlement',
    ],

    'admin_oversight' => [
        'heading' => '3. Admin Oversight',
        'p1' => 'All vendor campaigns.',
        'p2' => 'Admin can: approve (if review required), reject with reason. Prevent fraudulent or misleading campaign offers.',
    ],

    'commission_priority' => [
        'heading' => '4. Commission Resolution Priority',
        'tier1' => 'Per-product override rate',
        'tier2' => 'Campaign rate',
        'tier3' => 'Marketer tier rate',
        'tier4' => 'Platform default',
    ],
];
