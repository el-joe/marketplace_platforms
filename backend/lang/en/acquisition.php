<?php

return [
    'help' => [
        'title' => 'What is an acquisition commission?',
        'what' => 'A reward the platform pays to one of its own employees (an admin user) for bringing a vendor onto the platform. It is not charged to the vendor and does not change the vendor\'s platform commission or payout.',
        'who' => 'Beneficiary: the employee you choose as the vendor\'s "acquisition agent". Any active admin user can be chosen.',
        'how' => 'Activation: open a vendor (Vendors → vendor page → "Acquisition Agent" card) or use "Assign agent" on this page, then choose the employee, the rate, the duration and the monthly sales thresholds. Nothing is created automatically.',
        'earn' => 'Earnings: on the 1st of every month the system looks at the vendor\'s orders completed in the previous month (inside the deal period). If their number reaches the "monthly minimum", the employee earns the rate × the order subtotal on each completed order, up to the "monthly maximum" orders. Below the minimum, nothing is earned for that month.',
        'expiry' => 'Duration: the deal runs from its start date (by default the vendor\'s approval date) for the chosen number of months, then it expires by itself. It can be revoked at any time; earnings already recorded stay.',
        'payout' => 'Payment: earnings are recorded here and on the employee\'s "My acquisition commissions" page with the status "pending". They are not paid through vendor payouts or wallets; finance pays them outside the system (for example with salaries).',
        'vendor_card' => 'No employee is linked to this vendor. Assign the employee who brought the vendor to pay them an acquisition commission on the vendor\'s monthly sales.',
    ],
    'actions' => [
        'assign' => 'Assign agent',
    ],
    'fields' => [
        'vendor' => 'Vendor',
        'vendor_placeholder' => 'Search for a vendor…',
        'valid_from' => 'Start date',
        'valid_from_hint' => 'Defaults to the vendor\'s approval date. The deal ends after the chosen number of months.',
    ],
    'status' => [
        'active' => 'Active',
        'expired' => 'Expired',
        'revoked' => 'Revoked',
    ],
    'messages' => [
        'assigned' => 'Acquisition agent assigned.',
        'updated' => 'Acquisition commission updated.',
        'revoked' => 'Acquisition agent revoked.',
    ],
    'errors' => [
        'active_exists' => 'This vendor already has an active acquisition agent. Revoke it first to assign another one.',
        'already_expired' => 'With this start date and duration the deal would have ended on :date. Choose a later start date or a longer duration.',
        'not_active' => 'Only an active acquisition commission can be revoked.',
    ],
    'empty' => 'No acquisition commissions yet. Use "Assign agent" to link an employee to a vendor.',
];
