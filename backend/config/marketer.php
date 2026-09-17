<?php

/**
 * enhancement.md P-15 task 3: marketer listing price must stay within
 * min_price..max_price derived from the source listing's price ± X%.
 * No dedicated admin-settings UI exists for this yet, so it is a config
 * value (documented default: ±20%) rather than a `settings` table row —
 * revisit if/when an admin screen for marketer program rules is built.
 */
return [
    'listing_price_bound_pct' => (float) env('MARKETER_LISTING_PRICE_BOUND_PCT', 20),
];
