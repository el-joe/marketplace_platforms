<?php

return [
    'link' => 'Commission',
    'title' => 'Commission — :category',
    'parent' => 'Sub-category of :parent. Blank fields inherit from it.',
    'help' => [
        'platform' => 'Classified sales are agreed between the seller and the buyer outside the platform, so the platform takes no commission on the sale price itself. The platform earns from classifieds through the campaign fee (per country, for all classified sections) and the commission fee below, taken from every marketer commission earned in this category.',
        'marketer' => 'Influencers earn the fixed amount per campaign set here. Affiliate and broker rates are agreed per marketer on the marketer\'s profile.',
        'marketer_link' => 'Marketers',
        'inherit' => 'Leave a field blank to inherit it (parent section, then the country default). New categories inherit automatically. These are the same values as the category rows in Marketer Settings; changes apply to new campaigns and commissions only.',
        'defaults_link' => 'Country defaults (Marketer Settings)',
    ],
    'columns' => [
        'country' => 'Country',
        'influencer' => 'Influencer amount per campaign',
        'fee_percentage' => 'Platform commission fee (%)',
        'fee_fixed' => 'Platform commission fee (fixed)',
        'campaign_fee' => 'Campaign fee (all classifieds)',
    ],
    'inherits_value' => 'Blank = :value',
    'inherits' => 'Inherited',
    'campaign_fee_link' => 'Marketing fees',
    'saved' => 'Category commission settings saved.',
];
