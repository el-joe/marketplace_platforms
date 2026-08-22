<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vendor Tier Thresholds
    |--------------------------------------------------------------------------
    | These thresholds determine which tier a vendor falls into for flash sale
    | eligibility. Values are in the platform's base currency unit (cents).
    | Tune these without a code change — they are read at runtime.
    */

    'tier_thresholds' => [
        'bronze' => [
            'min_total_sales'      => 0,
            'min_rating'           => 0.0,
            'min_sla_compliance'   => 0.0,
            'max_strikes'          => PHP_INT_MAX,
        ],
        'silver' => [
            'min_total_sales'      => 5_000_000,   // 50,000 in base currency (×100)
            'min_rating'           => 3.5,
            'min_sla_compliance'   => 0.0,
            'max_strikes'          => PHP_INT_MAX,
        ],
        'gold' => [
            'min_total_sales'      => 20_000_000,  // 200,000 in base currency
            'min_rating'           => 4.0,
            'min_sla_compliance'   => 90.0,
            'max_strikes'          => PHP_INT_MAX,
        ],
        'platinum' => [
            'min_total_sales'      => 50_000_000,  // 500,000 in base currency
            'min_rating'           => 4.5,
            'min_sla_compliance'   => 95.0,
            'max_strikes'          => 0,
        ],
    ],

];
