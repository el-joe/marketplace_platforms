<?php

return [
    'title' => 'Payments & Wallets',

    'methods' => [
        'heading' => '1. Payment Methods',
        'availability' => 'Per-country availability configured in country_payment_methods',
    ],

    'gateways' => [
        'heading' => '2. Payment Gateways',
        'configured' => 'Configured via /admin/payment-gateways with credential Strategy Pattern',
        'test' => 'Test connection available per gateway',
        'webhooks' => 'Webhook logs viewable per method',
    ],

    'wallet' => [
        'heading' => '3. Customer Wallet',
        'one_per_currency' => 'One wallet per currency (polymorphic — owner_type=\'customer\')',
        'ledger' => 'wallet_transactions: append-only ledger with balance_after for audit trail',
        'frozen' => 'Wallet can be frozen by admin (suspicion/fraud)',
        'withdrawal' => 'Withdrawal requests → admin approves → bank transfer processed',
    ],

    'cod_flow' => [
        'heading' => '4. COD Flow',
        'collect' => 'Agent collects cash at delivery',
        'settlements' => 'delivery_agent_cod_settlements: tracks what each agent owes',
        'generate' => 'Admin generates settlement → marks settled',
        'note' => 'Only then: sub_order.cod_remittance_confirmed = true → vendor payout unlocked',
    ],

    'refunds' => [
        'heading' => '5. Refunds',
        'virtual_note' => '(VIRTUAL GENERATED — never write)',
        'card_label' => 'Card:',
        'card' => 'reversed to card (5–10 business days)',
        'wallet_label' => 'Wallet:',
        'wallet' => 'instant',
        'cod_label' => 'COD:',
        'cod' => 'added to customer wallet (no cash back)',
    ],

    'gift_cards' => [
        'heading' => '6. Gift Cards',
        'create' => 'Admin creates with value + currency + expiry',
        'redeem' => 'Customer redeems at checkout (full or partial use)',
        'manage' => 'Admin can cancel, extend expiry, adjust balance',
    ],

    'money_rules' => [
        'heading' => '7. Money Rules (Critical)',
        'bigint' => 'All values: BIGINT base currency (SAR, AED, OMR, KWD, QAR, BHD, EGP, JOD)',
        'no_divide' => 'NO /100 or ×100 anywhere (except Thawani baisa conversion, only inside ThawaniGateway)',
        'no_sum' => 'NEVER sum across currencies — always GROUP BY currency first',
        'rounding' => 'FLOOR() for percentage calcs, ROUND() for flat rates',
    ],
];
