<?php

return [
    'title' => 'Delivery Agent Panel',
    'meta' => 'URL: <code>delivery.{domain}</code> &middot; Guard: <code>delivery_agents</code>',

    'dashboard' => [
        'title' => 'Dashboard',
        'summary' => "Today's assignments, earnings today, active status toggle",
    ],

    'assignments' => [
        'title' => 'Assignments',
        'assignments' => 'Delivery tasks assigned to this agent',
        'actions' => 'Actions in order: accept &rarr; picked-up &rarr; deliver | fail',
        'otp' => 'OTP shown to agent for visual verification with customer (agent does NOT enter it)',
    ],

    'location' => [
        'title' => 'Location &amp; Availability',
        'location' => 'Update live GPS coordinates',
        'availability' => 'Toggle available/unavailable for new assignments',
    ],

    'earnings' => [
        'title' => 'Earnings',
        'history' => 'Per-assignment earnings history',
        'summary' => 'Weekly/monthly summary',
    ],

    'wallet' => [
        'title' => 'Wallet',
        'balance' => 'Agent wallet balance',
        'withdraw' => 'Request withdrawal to bank',
    ],

    'cod_settlements' => [
        'title' => 'COD Settlements',
        'settlements' => 'Cash collected from COD deliveries; settlement history',
    ],

    'profile' => [
        'title' => 'Profile',
        'profile' => 'Agent info and documents',
    ],
];
