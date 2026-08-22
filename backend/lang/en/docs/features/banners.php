<?php

return [
    'title' => 'Banners',

    'what_they_are' => [
        'heading' => '1. What Banners Are',
        'body1' => 'Promotional image banners that appear in fixed placement slots across the storefront and all panel homepages.',
        'body2' => 'Different from Page Builder ad_images blocks — banners are managed centrally with named placements.',
    ],

    'placements' => [
        'heading' => '2. Placements',
        'body' => 'Lists all available placement slots. Each banner is assigned to one placement.',
        'examples_label' => 'Placement examples',
    ],

    'lifecycle' => [
        'heading' => '3. Banner Lifecycle',
        'flow' => 'create → upload image → set dates (start/end) → set target URL → assign placement',
        'active_label' => 'Active:',
        'active' => 'now() between start_date and end_date',
        'expired_label' => 'Expired:',
        'expired' => 'end_date passed (kept for history)',
        'rotation' => 'Multiple banners per placement = rotation (sorted by sort_order or random)',
    ],

    'admin_actions' => [
        'heading' => '4. Admin Actions',
        'duplicate_label' => 'Duplicate:',
        'duplicate' => 'clone a banner for rapid A/B testing',
        'bulk_label' => 'Bulk:',
        'bulk' => 'activate/deactivate/delete multiple at once',
        'upload_label' => 'Upload image:',
        'upload' => 'separate FilePond endpoint per banner',
    ],
];
