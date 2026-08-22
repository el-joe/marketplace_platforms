<?php

return [
    'title' => 'Warehouses & FBN',

    'types' => [
        'heading' => '1. Warehouse Types',
        'platform_fbn' => 'Platform fulfillment center',
        'vendor_owned' => "Vendor's registered warehouse (FBM/FBP)",
        'cross_dock' => 'Transit hub',
    ],

    'inventory_columns' => [
        'heading' => '2. Inventory Columns',
        'on_hand' => 'physical stock (writable)',
        'reserved' => 'in carts / pending orders (writable)',
        'available' => 'VIRTUAL =',
        'warning' => 'NEVER WRITE TO quantity_available.',
    ],

    'movements' => [
        'heading' => '3. inventory_movements — APPEND ONLY',
        'body' => 'Every stock change creates a new row. No updates or deletes.',
        'types' => 'Types:',
        'received_note' => 'received_at on inbound movement: starts the free storage clock.',
    ],

    'inbound_flow' => [
        'heading' => '4. FBN Inbound Request Flow',
        'submit' => 'Vendor submits → status: draft → submitted',
        'approve' => 'Admin approves → approved',
        'ship' => 'Vendor ships → adds tracking number',
        'receive' => 'Warehouse receives → status: received',
        'movement_created' => 'inventory_movement: type=inbound created, received_at=NOW',
        'on_hand_incremented' => 'quantity_on_hand incremented',
        'storage_begins' => 'Free storage period begins',
        'orderable' => 'Listing becomes orderable when quantity_available > 0',
    ],

    'free_storage' => [
        'heading' => '5. Free Storage Period',
        'default' => 'warehouses.free_storage_days (default: 30)',
    ],

    'overage_fees' => [
        'heading' => '6. Daily Overage Fees',
        'after' => 'After free_period_ends_at:',
        'job' => 'Job runs at 01:00 daily → inserts fbn_daily_overage_fees row (idempotent)',
        'monthly' => 'Monthly: GenerateFbnStorageFeesJob aggregates → creates invoice → deducted from payout',
    ],

    'transfers' => [
        'heading' => '7. Inventory Transfers',
        'body' => 'Move stock between warehouses.',
    ],
];
