<?php

return [
    /*
     * enhancement.md P-09 task 4: the post-purchase "buy a warranty after
     * delivery" flow (POST warranty/purchases) is only allowed within this
     * many days of the item's delivery date. Default: 30 days.
     */
    'post_purchase_window_days' => (int) env('WARRANTY_POST_PURCHASE_WINDOW_DAYS', 30),
];
