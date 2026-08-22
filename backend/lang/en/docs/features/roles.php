<?php

return [
    'title' => 'Roles & Permissions',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => 'What It Is',
        'body' => 'Role-based access control built on Spatie Laravel Permission, applied independently per guard so admin, vendor, travel agency, marketer, delivery agent, and shipping company supervisor accounts each get their own role/permission universe.',
    ],

    'architecture' => [
        'heading' => '1. Architecture',
        'guards' => 'Guards',
        'per_guard' => 'Each guard has its own roles and permissions',
        'must_match' => 'must match',
        'model_uuid' => 'uses',
        'not_default' => 'not the package default',
    ],

    'admin_roles' => [
        'heading' => '2. Admin Roles',
        'managed_at' => 'Managed at',
        'system_roles' => 'System roles cannot be deleted',
        'etc' => 'etc.',
        'custom_roles' => 'Custom roles are created by an admin with any combination of permissions',
        'permission_format' => 'Permission format',
        'eg' => 'e.g.',
    ],

    'vendor_roles' => [
        'heading' => '3. Vendor Roles',
        'scoped' => 'Scoped per vendor using the prefix',
        'system_roles' => 'System roles',
        'custom_roles' => 'Custom roles are created from the partner panel',
        'applied_via' => 'Applied via the',
        'middleware' => 'middleware on partner panel routes',
    ],

    'agency_roles' => [
        'heading' => '4. Travel Agency Roles',
        'scoped' => 'Scoped per agency using the prefix',
        'system_roles' => 'System roles',
        'owner_bypass' => 'The Owner bypasses all permission checks',
        'model' => 'model',
        'members' => 'Members',
        'checked_via' => 'are checked via Spatie',
    ],

    'restriction' => [
        'heading' => '5. Admin Permission',
        'description' => 'A restriction permission &mdash; limits an admin to only see their assigned vendor(s)',
        'assigned_manually' => 'Assigned manually to specific admin accounts',
        'hides_financial' => 'Hides financial data (bank accounts, total sales, payout) from a restricted view',
        'never_granted' => 'Never granted to',
        'privilege_note' => 'it is a restriction, not a privilege.',
    ],

    'rules' => [
        'heading' => 'Who Uses It & Key Rules',
        'every_panel' => 'Every panel (admin, partner, travel agency, marketer, delivery, carrier) enforces its own guard-scoped roles',
        'protected' => 'System roles are protected from deletion across all guards, only custom roles can be removed',
        'isolation' => 'Vendor- and agency-scoped role names must never collide across tenants &mdash; the prefix guarantees isolation',
    ],
];
