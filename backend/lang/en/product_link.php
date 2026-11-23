<?php

return [
    'nav' => 'Product link / QR commission',
    'admin' => [
        'title' => 'Commission discount for sales through the vendor\'s product link / QR',
        'help' => [
            'what' => 'Vendors can copy a personal link or download a QR code for each of their products (Partner panel → Products → "My product link / QR"). When a customer opens that link or scans that code and then buys the same product from the same vendor, the platform commission on that item is reduced as set below.',
            'qualifying' => 'Only the vendor\'s own signed link / QR counts. A normal storefront link, another vendor\'s offer of the same product, a marketer\'s listing, or a purchase after the attribution window keeps the normal commission.',
            'precedence' => 'Last touch: if the customer clicked a marketer\'s referral link after the vendor\'s link, the marketer brought the customer and no discount is given. The marketer commission rules themselves are not changed.',
            'scope' => 'Applies only to orders placed after you save. Existing orders, statements and payouts are never recalculated.',
        ],
        'fields' => [
            'active' => 'Rule active',
            'sale_models' => 'Sale models',
            'discount_type' => 'Discount type',
            'discount_percent' => 'Percent off the commission',
            'discount_fixed' => 'Fixed amount off per unit',
            'window' => 'Attribution window (days)',
        ],
        'types' => [
            'percent' => 'Percent off the platform commission',
            'fixed' => 'Fixed amount off (per unit)',
            'both' => 'Percent and fixed amount',
            'zero' => 'Zero commission',
        ],
        'models' => [
            'fbm' => 'FBM — vendor ships',
            'fbn' => 'FBN — fulfilled by Nawy',
            'cross_dock' => 'Cross-dock',
        ],
        'not_configured' => 'Not configured — normal commission',
        'save' => 'Save',
        'saved' => 'Product link commission rule saved for :country.',
        'errors' => [
            'amount_required' => 'Enter an amount above zero for the selected discount type.',
        ],
    ],
    'partner' => [
        'button' => 'My product link / QR',
        'title' => 'Your product link and QR code',
        'intro' => 'Share this link or print this QR code on the product, its packaging or your ads. Customers who buy this product from you through it can lower the platform commission you pay on that sale.',
        'rule_active' => 'Current rule in your country: :discount, for orders placed within :days days of opening the link.',
        'rule_inactive' => 'There is no commission discount in your country at the moment; the link still brings customers straight to your product.',
        'copy' => 'Copy link',
        'download_qr' => 'Download QR',
        'copied' => 'Link copied.',
    ],
    'order' => [
        'badge' => 'Via your barcode / link',
        'badge_admin' => 'Via vendor\'s product link / QR',
        'discount' => 'Commission discount (product link)',
    ],
    'describe' => [
        'percent' => ':percent% off the platform commission',
        'fixed' => ':amount :currency off per unit',
        'both' => ':percent% + :amount :currency per unit off the platform commission',
        'zero' => 'no platform commission',
    ],
];
