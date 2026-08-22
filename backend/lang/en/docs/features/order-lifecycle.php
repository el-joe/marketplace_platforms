<?php

return [
    'title' => 'Order Lifecycle',

    'structure' => [
        'heading' => '1. Order Structure',
        'intro' => 'One customer checkout creates:',
        'row_master' => '1 order row (master)',
        'row_sub' => '1 sub_order per vendor in the cart',
        'row_items' => 'N order_items (one per listing × quantity)',
        'settlement' => 'All financial settlement happens at sub_order level.',
    ],

    'status_flow' => [
        'heading' => '2. Status Flow',
        'master_order' => 'Master Order',
        'branches_to' => 'branches to:',
        'sub_order' => 'Sub Order',
    ],

    'cart_phase' => [
        'heading' => '3. Cart Phase',
        'lock' => 'Inventory soft-locked via cart_inventory_locks (TTL 15 minutes)',
        'guest' => 'Guest carts use X-Cart-Token header; merged on login',
        'oversell' => 'Lock prevents oversell during concurrent checkout sessions',
    ],

    'checkout_calc' => [
        'heading' => '4. Checkout Calculation',
        'subtotal' => 'Subtotal per currency (never sum across currencies)',
        'commission' => 'deducted from vendor',
        'shipping_fee' => 'Shipping fee via ShippingCalculationService',
        'subsidy' => 'Exceptional zone subsidy applied if eligible',
        'coupon' => 'Coupon / discount applied (max 2: 1 platform + 1 vendor)',
        'cod_fee' => 'COD fee added if payment_method = \'cod\'',
        'tax' => 'Tax / VAT per country rules',
        'warranty' => 'Warranty plan cost if selected',
        'gateway_fee' => 'split across sub_orders',
    ],

    'payment' => [
        'heading' => '5. Payment',
        'supported_methods' => 'Supported methods:',
        'thawani_label' => 'Thawani note:',
        'thawani_note' => 'amounts sent in baisa (OMR subunit) — ONLY inside ThawaniGateway class.',
    ],

    'fulfillment' => [
        'heading' => '6. Fulfillment Flows (FBM vs FBN)',
        'fbm_label' => 'FBM:',
        'fbm' => 'Vendor prepares → Agent picks up from vendor → Delivers to customer',
        'fbn_label' => 'FBN:',
        'fbn' => 'Platform warehouse picks/packs → Agent picks up → Delivers',
    ],

    'delivery_otp' => [
        'heading' => '7. Delivery OTP',
        'body' => '4-digit code shown to the AGENT on their screen. Customer reads it aloud — agent visually matches. Agent does NOT type it — it is display-only verification.',
    ],

    'auto_completion' => [
        'heading' => '8. Auto-Completion',
        'intro' => 'Daily job: if sub_order.status = \'delivered\' and now > delivered_at + return_window_days:',
        'status_completed' => 'status = \'completed\'',
        'payout_unlocked' => 'vendor_payout unlocked',
        'conversion_approved' => 'marketer conversion approved',
    ],

    'cod_gate' => [
        'heading' => '9. COD Gate',
        'body' => 'Vendor payout for COD sub_orders is BLOCKED until delivery agent has remitted cash. cod_remittance_confirmed must be true before vendor receives their payout.',
    ],

    'cancellation' => [
        'heading' => '10. Cancellation',
        'customer_label' => 'Customer:',
        'customer' => "before 'shipped' status only",
        'vendor_label' => 'Vendor:',
        'vendor' => 'can cancel own sub_order (penalty on score)',
        'admin_label' => 'Admin:',
        'admin' => 'override cancel at any stage',
        'on_cancel' => 'On cancel: inventory released, refund initiated, marketer conversion reversed.',
    ],

    'return_flow' => [
        'heading' => '11. Return Flow',
        'flow' => 'Customer submits → Admin/vendor reviews → Agent pickup → Warehouse inspection',
        'outcomes_label' => 'Inspection outcomes:',
        'good_label' => 'Good:',
        'good' => 'full refund',
        'damaged_customer_label' => 'Damaged by customer:',
        'damaged_customer' => 'partial/no refund',
        'damaged_transit_label' => 'Damaged in transit:',
        'damaged_transit' => 'full refund, carrier investigated',
    ],

    'dispute' => [
        'heading' => '12. Dispute',
        'flow' => 'Customer opens → Admin assigns → Vendor responds → Admin mediates → Resolution',
        'resolution_types_label' => 'Resolution types:',
    ],

    'warranty_claim' => [
        'heading' => '13. Warranty Claim',
        'standard_label' => 'Standard:',
        'standard' => 'vendor covers, filed within warranty_expires_at',
        'extended_label' => 'Extended (plan):',
        'extended' => 'platform covers, customer purchased plan at checkout',
        'vendor_panel' => 'Vendor panel is hard-scoped: cannot see admin-listing warranty claims (403).',
    ],
];
