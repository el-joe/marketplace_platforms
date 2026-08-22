<?php

return [
    'title' => 'Finance & Payouts',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => 'What It Is',
        'p1' => 'The financial backbone of the platform: vendor/agent/marketer payouts, a double-entry ledger, raw transaction records, and vendor subscription billing.',
    ],

    'vendor_payouts' => [
        'heading' => 'Vendor Payouts',
        'eligibility_label' => 'Eligibility',
        'eligibility_desc' => 'COD settlement gate passed',
        'grouping' => 'groups by vendor + currency — never sums across currencies',
        'admin_flow_label' => 'Admin flow',
        'approve_process' => 'approve → process',
        'process_note' => 'process triggers the bank transfer',
        'can_also_label' => 'Admin can also',
        'hold' => 'hold',
        'hold_note' => 'pending investigation',
        'or' => 'or',
        'recalculate' => 'recalculate',
    ],

    'cod_gate' => [
        'heading' => 'COD Settlement Gate',
        'must_be' => 'must be',
        'p1' => 'before a COD sub-order can be paid out',
        'p2' => 'Set automatically when an admin marks a COD settlement as settled',
        'notice' => 'Vendors see an amber notice in the partner panel while COD funds have not yet been remitted.',
    ],

    'delivery_payouts' => [
        'heading' => 'Delivery Agent Payouts',
        'flow' => 'generate → approve → process',
        'p1' => 'based on per-delivery earning rates plus bonuses.',
    ],

    'marketer_payouts' => [
        'heading' => 'Marketer Payouts',
        'flow' => 'generate → approve → process',
        'p1' => 'based on approved',
        'p2' => 'in the period.',
    ],

    'ledger' => [
        'heading' => 'Ledger',
        'p1' => 'Double-entry ledger at',
        'p2' => 'Every financial event writes a debit row and a credit row; transaction groups link related ledger rows together.',
    ],

    'transactions' => [
        'heading' => 'Transactions',
        'p1' => 'Raw transaction log at',
        'p2' => "one level above the ledger's debit/credit rows.",
    ],

    'subscriptions' => [
        'heading' => 'Subscriptions (Vendor Plans)',
        'item1' => 'view active vendor subscriptions',
        'plans_label' => 'Plans',
        'plans_desc' => 'create monthly/annual plans, priced per country',
        'invoices_label' => 'Invoices',
        'invoices_desc' => 'monthly invoices generated per vendor',
        'item4' => 'Admin can manually subscribe a vendor or cancel their subscription',
    ],

    'who_rules' => [
        'heading' => 'Who Uses It & Key Rules',
        'admins_label' => 'Admins',
        'admins_desc' => 'run and approve every payout flow; no payout is auto-processed without an approval step',
        'vendors_label' => 'Vendors',
        'vendors_desc' => 'only see payout status and COD notices, never raw ledger entries',
        'currency_rule' => 'Currency mixing across a single payout is a hard invariant — the calculation service enforces per-currency grouping',
    ],
];
