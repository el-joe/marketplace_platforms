<?php

return [
    // Flat platform commission rate applied to completed booking revenue in
    // reports. No stored commission column exists on travel_bookings, so this
    // is computed on the fly: commission = total_price * rate.
    'platform_commission_rate' => (float) env('TRAVEL_PLATFORM_COMMISSION_RATE', 0.10),
];
