<?php

return [
    // FBN (Fulfilled by Noon) base shipping fee, in minor currency units (cents).
    // 0 = free (current behaviour, platform bears the cost). Only the warehouse
    // surcharge (WarehouseShippingSurchargeService) is added on top either way.
    'fbn_base_shipping_fee' => (int) env('FBN_BASE_SHIPPING_FEE', 0),
];
