<?php

return [
    'title' => 'Radio Channels',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => '1. What It Is',
        'embedded' => 'Online radio streams embedded in the storefront, creating an ambient shopping experience',
        'stream_url' => 'Each channel has a live audio stream URL',
        'admin_schedules' => 'Admin schedules programming slots',
    ],

    'how_it_works' => [
        'heading' => '2. How It Works',
        'step1' => 'Admin creates a channel &rarr; adds stream URL + cover image + description',
        'step2' => 'Admin schedules slots (time-based programming schedule)',
        'step3' => 'Customers listen at',
        'step4' => 'Session tracking: JS reports listen duration per session',
    ],

    'admin_management' => [
        'heading' => '3. Admin Management',
        'channel_crud' => 'channel CRUD',
        'view_schedule' => 'view weekly programming schedule',
        'edit_slots' => 'add/edit/delete time slots',
        'calendar_json' => 'Schedule events returned as calendar-compatible JSON for frontend display',
    ],

    'rules' => [
        'heading' => 'Who Uses It & Key Rules',
        'admin_label' => 'Admin',
        'only_manager' => 'is the only one who manages channels and slots; customers only listen',
        'per_country' => "A channel's storefront route is per-country",
        'not_global' => 'not global',
        'slot_scheduling' => 'Slot scheduling is time-based per channel, so overlapping slots on the same channel should be avoided in the schedule UI',
    ],
];
