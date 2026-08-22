<?php

return [
    'title' => 'Marketer Panel',
    'meta_url' => 'URL: <code>marketer.{domain}</code>',
    'meta_types' => 'Two marketer types: <strong>Affiliate</strong> — commission-based, uses promo codes and tracking links &middot; <strong>Influencer</strong> — flat-fee deals with content deliverables',

    'dashboard' => [
        'title' => 'Dashboard',
        'summary' => 'Earnings summary, active campaigns, recent conversions, pending invitations',
    ],

    'analytics' => [
        'title' => 'Analytics',
        'analytics' => 'Cross-campaign performance: clicks, conversions, earnings by date range',
    ],

    'store' => [
        'title' => 'My Store',
        'store' => 'Public profile management: bio, social links, audience stats, cover image',
        'preview' => 'How vendors and customers see the profile',
        'public_url' => 'Public URL:',
    ],

    'campaigns' => [
        'title' => 'Campaigns',
        'campaigns' => 'Self-initiated campaigns to promote products/classifieds/travel packages',
        'create' => 'Create: choose campaign type, add products/travel packages, set attribution model',
        'track' => 'Track: clicks, conversions, earnings per campaign',
        'actions' => 'Actions: pause, resume, cancel, resubmit after rejection',
    ],

    'invitations' => [
        'title' => 'Vendor Invitations',
        'invitations' => 'Vendors invite marketers to promote their campaigns',
        'respond' => 'Accept or decline with optional note',
    ],

    'admin_offers' => [
        'title' => 'Admin Offers',
        'offers' => 'Admin sends direct campaign offers to specific marketers',
        'respond' => 'Accept or decline with reason note',
    ],

    'qr_codes' => [
        'title' => 'QR Codes',
        'qr_codes' => 'Generate trackable QR codes linking to campaigns',
        'download' => 'Download for offline marketing use',
    ],

    'promo_codes' => [
        'title' => 'Promo Codes',
        'affiliate_only' => '(Affiliate only)',
        'request' => 'Request platform-assigned affiliate codes',
        'usage' => 'Used at customer checkout; attribution tied to this marketer',
    ],

    'deals' => [
        'title' => 'Deals + Deliverables',
        'influencer_only' => '(Influencer only)',
        'view' => 'View flat-fee content deals proposed by admin',
        'submit' => 'Accept/reject deals; submit content deliverables for review',
        'media_kit' => 'Manage media kit shown to potential partners',
    ],

    'samples' => [
        'title' => 'Sample Requests',
        'samples' => 'Request product samples for review content creation',
    ],

    'earnings' => [
        'title' => 'Earnings',
        'history' => 'Commission earnings history and breakdown',
        'summary' => 'Aggregated view by period and campaign',
    ],

    'wallet' => [
        'title' => 'Wallet',
        'balance' => 'Platform wallet balance',
        'withdraw' => 'Request withdrawal to bank account',
    ],

    'secret_promotions' => [
        'title' => 'Secret Promotions',
        'promotions' => "Exclusive promotions only visible to this marketer's audience",
    ],
];
