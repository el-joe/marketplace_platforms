<?php

return [
    'title' => 'Carrier (Shipping Supervisor) Panel',
    'meta' => 'URL: <code>carrier.{domain}</code> &middot; Guard: <code>shipping_company_supervisors</code> &middot; Belongs to: <code>shipping_companies</code>',

    'dashboard' => [
        'title' => 'Dashboard',
        'summary' => 'Active deliveries, agent count, SLA metrics',
    ],

    'agents' => [
        'title' => 'Agents',
        'agents' => 'Create/manage delivery agents for this carrier',
        'unlimited' => 'Unlimited agents — no cap enforced',
        'toggle' => 'Suspend/activate agents',
    ],

    'supervisors' => [
        'title' => 'Supervisors',
        'supervisors' => 'Manage supervisors within the same shipping company',
        'owner_only' => 'Only owner-level supervisor can create other supervisors (gated in controller)',
    ],

    'assignments' => [
        'title' => 'Assignments',
        'unassigned' => 'Orders not yet assigned to an agent',
        'assign' => 'Assign shipments to agents; reassign if needed',
        'all' => 'All assignments with status tracking',
        'detail' => 'Detail view per assignment',
    ],
];
