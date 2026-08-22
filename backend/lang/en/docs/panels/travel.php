<?php

return [
    'title' => 'Travel Agency Panel',
    'meta_guard' => 'URL: <code>travel-agency.{domain}</code> &middot; Guard: <code>travel_agency</code> (single-model — the TravelAgency row itself is the owner)',
    'meta_team' => 'Team members: <code>TravelAgencyMember</code> (separate table, same guard via custom UserProvider)',

    'dashboard' => [
        'title' => 'Dashboard',
        'summary' => 'Bookings today, revenue this month, pending inquiries, packages in review',
    ],

    'packages' => [
        'title' => 'Travel Packages',
        'crud' => 'CRUD for travel packages',
        'create' => 'Create: destination, dates, price, inclusions, media gallery, itinerary',
        'submit' => 'Submit for review &rarr; Admin approves &rarr; Package goes live on storefront',
        'withdraw' => 'Withdraw: pause a live package',
    ],

    'bookings' => [
        'title' => 'Bookings',
        'all' => "All bookings for this agency's packages",
        'status' => 'Update status: pending_documents &rarr; confirmed &rarr; completed | cancelled',
        'manual' => 'Create bookings manually on behalf of customers',
    ],

    'inquiries' => [
        'title' => 'Inquiries',
        'inquiries' => 'Customer inquiries from the storefront travel pages',
        'actions' => 'Mark as contacted, convert to booking, close',
    ],

    'marketer_campaigns' => [
        'title' => 'Marketer Campaigns',
        'campaigns' => 'Create and manage campaigns to promote packages via marketer network',
        'invite' => 'Invite specific marketers to campaigns; revoke invitations',
    ],

    'reports' => [
        'title' => 'Reports',
        'revenue' => 'Revenue by period, grouped by currency',
        'bookings' => 'Booking counts, status breakdown, filters',
        'packages' => 'Package-level conversion and revenue stats',
    ],

    'team' => [
        'title' => 'Team',
        'team' => 'Manage staff members with role-based access',
    ],

    'roles' => [
        'title' => 'Roles',
        'roles' => 'Create custom roles scoped to this agency',
        'system_roles' => 'System roles: <code>agency_owner</code> (all permissions), <code>agency_manager</code>, <code>agency_staff</code>',
    ],

    'bank_accounts' => [
        'title' => 'Bank Accounts',
        'flagged' => 'Feature-flagged (hidden until enabled in .env)',
    ],

    'profile' => [
        'title' => 'Profile',
        'profile' => 'Agency name, logo, contact info, password',
    ],
];
