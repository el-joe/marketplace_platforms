<?php

return [
    'sections' => [
        'general' => 'General',
        'status' => 'Status & visibility',
        'countries' => 'Countries',
        'attributes' => 'Attributes',
        'shipping' => 'Shipping methods',
        'commissions' => 'Commissions',
        'seo' => 'SEO',
        'marketers' => 'Marketers',
        'nav_aria' => 'Category form sections',
    ],

    'save_all' => 'Save all',
    'create_all' => 'Create category',
    'saving' => 'Saving…',
    'save_all_hint' => 'One button saves every section on this page together. If something is wrong nothing is saved, and the section with the problem is highlighted.',
    'unsaved_changes' => 'You have unsaved changes',
    'unsaved_leave_confirm' => 'You have unsaved changes. Leave this page without saving?',
    'all_saved' => 'All changes saved',
    'errors_title' => 'Please fix the highlighted fields:',
    'errors_in_section' => ':count error(s) in this section',
    'fix_errors' => 'Some fields need attention. Nothing was saved.',
    'save_failed' => 'Saving failed. Please try again.',

    'flags' => [
        'active_hint' => 'Off = the category is switched off on the storefront: its page returns "not found" (also by direct link), its subcategories go with it, and products are not listed under it.',
        'visible_hint' => 'Off = hidden from menus, navigation and category lists, but the page still opens by direct link (while Active is on).',
        'featured_hint' => 'On = shown in the homepage "category pills" section when that block has no hand-picked categories (max :max featured). Needs Active and Visible.',
        'has_filters_hint' => 'Shows the filter sidebar (price, brand, attributes) on this category\'s page.',
    ],

    'availability' => [
        'title' => 'Availability by country',
        'mode_all' => 'All countries',
        'mode_only' => 'Only selected countries',
        'mode_except' => 'All countries except selected',
        'pick_countries' => 'Select countries',
        'hint' => 'Stored as the country\'s "Category exceptions" (Countries → edit → Category exceptions) — the same data, so both pages always agree; whichever is saved last wins. New countries start as available until you exclude them.',
        'inherit_hint' => 'Subcategories inherit their parent: a subcategory never shows in a country where its parent is unavailable. Here you can only restrict it further.',
        'inherited_title' => 'Already unavailable through a parent category:',
        'status_draft' => 'Draft',
        'status_hidden' => 'Hidden',
        'select_all' => 'Select all',
        'country_page_hint' => 'Same data as the "Countries" section of each category\'s form (All / Only selected / All except selected): a change here shows there and vice versa; the last save wins. A category hidden here also hides its subcategories in this country.',
        'clear' => 'Clear',
    ],

    'shipping' => [
        'add' => 'Add',
        'choose' => 'Choose a shipping method…',
        'none' => 'No shipping methods yet. Products in this category will have no delivery option until you add one.',
        'move_up' => 'Move up',
        'move_down' => 'Move down',
        'order_hint' => 'Order = priority shown to customers. Exactly one method can be the default.',
    ],

    'commissions' => [
        'country_title' => 'Commission per country',
        'country_hint' => 'Leave empty to inherit the category default. Saved with the rest of the form.',
        'tiers_title' => 'Commission tiers by price',
        'add_tier' => 'Add tier',
        'remove_tier' => 'Remove',
        'no_tiers' => 'No tiers — the flat rate applies.',
        'open_ended' => 'Empty = no upper limit',
    ],

    'image' => [
        'choose' => 'Choose image',
        'replace' => 'Replace image',
        'remove' => 'Remove image',
        'will_remove' => 'The image will be removed when you save.',
        'pending' => 'New image — saved with the form.',
    ],

    'attr' => [
        'commission_rate' => 'commission rate',
        'price_from' => 'price from',
        'price_to' => 'price to',
        'min_commission' => 'minimum commission',
        'shipping_method' => 'shipping method',
        'countries' => 'countries',
        'percentage' => 'percentage',
        'fixed_fee' => 'fixed fee',
    ],

    'errors' => [
        'parent_is_descendant' => 'A category cannot be moved under one of its own subcategories.',
        'slug_taken' => 'This slug is already used by another category or page.',
        'featured_limit' => 'At most :max categories can be featured. Un-feature another category first.',
        'one_default_shipping' => 'Only one shipping method can be the default.',
        'pick_countries' => 'Select at least one country for this option.',
    ],
];
