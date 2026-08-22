<?php

return [
    'title' => 'Shipping & Zones',

    'architecture' => [
        'heading' => '1. Architecture',
        'layer' => 'Layer',
        'purpose' => 'Purpose',
        'zones_desc' => 'Geographic groupings of cities within a country',
        'methods_desc' => 'Carrier/service options available per zone',
        'rates_desc' => 'Base fee + per-kg rate for a zone/method pair',
        'slabs_desc' => 'Extra fee tiers layered on top of the rate for heavy items',
    ],

    'weight_calc' => [
        'heading' => '2. Weight Calculation',
        'divisor' => 'Standard divisor:',
        'divisor_note' => '(configurable per zone/method)',
        'example_label' => 'Example:',
        'example' => '0.5kg actual, 40×30×20cm → volumetric = 4.8kg → billed at 4.8kg',
    ],

    'rate_calc' => [
        'heading' => '3. Rate Calculation',
    ],

    'zones' => [
        'heading' => '4. Shipping Zones',
        'body' => 'Geographic groupings within each country. Cities assigned to zones in admin. Customer address → zone lookup → rate lookup.',
    ],

    'exceptional_zones' => [
        'heading' => '5. Exceptional Zones',
        'opt_in' => 'vendor opts in to serve a specific zone',
        'split' => 'admin sets vendor_share + admin_support split',
        'checkout_note' => 'At checkout: customer sees FREE DELIVERY. Behind the scenes: vendor_share deducted from vendor payout, platform absorbs admin_support.',
    ],

    'free_threshold' => [
        'heading' => '6. Free Shipping Threshold',
        'settings_key' => 'Settings key:',
        'rule' => 'If order subtotal ≥ threshold: shipping_fee = 0 (platform absorbs)',
    ],

    'weight_slabs' => [
        'heading' => '7. Weight Slabs',
        'intro' => 'Extra fee tiers for heavy items:',
        'special_handling' => 'special handling',
    ],

    'surcharges' => [
        'heading' => '8. Surcharges (FBN)',
        'body' => 'Warehouse-level outbound surcharges on FBN shipments',
    ],

    'delivery_date' => [
        'heading' => '9. Estimated Delivery Date',
        'exclusions' => 'Excludes Fridays and country public holidays.',
    ],
];
