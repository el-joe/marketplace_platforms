<?php

return [
    'api' => [
        'category_unavailable' => 'This category is not available. Please choose another one.',
    ],

    'admin' => [
        'image_icon' => 'Image / icon',
        'appears_on' => 'Appears on',
        'countries' => 'Countries',
        'all_countries' => 'All countries',
        'no_placement' => 'Not shown (link only)',
        'hidden_badge' => 'Hidden',
        'description_en' => 'Description (English)',
        'description_ar' => 'Description (Arabic)',
        'image' => 'Category image',
        'image_help' => 'JPG, PNG or WebP, up to 4 MB. Shown on the category landing page and the home page block.',
        'remove_image' => 'Remove the current image',
        'icon_help' => 'Optional short icon (emoji or code). Kept for the menu.',
        'slug_help' => 'The slug is the storefront address of the category (/classified/slug). Use lowercase letters, numbers and dashes. Changing it later changes the public URL.',
        'placement_title' => 'Where this section appears on the website',
        'placement_help' => 'Only top-level sections can be placed. Sub-categories always appear under their section. A section with nothing ticked stays reachable by its link only.',
        'countries_title' => 'Countries',
        'countries_help' => 'Leave all unticked to offer the category in every country. Sub-categories inherit the restrictions of their section.',
        'deactivate_confirm' => 'Deactivate this category? It and its sub-categories disappear from the website. No listing, contract or signature is deleted, and you can activate it again at any time.',
        'deactivated_hint' => 'The category and its sub-categories are hidden from the website. Existing listings and signed contracts are untouched.',
        'delete_hint' => 'Only unused categories can be deleted. To take a category off the website without losing any data, deactivate it instead.',
        'cannot_delete' => 'This category cannot be deleted. :reasons Deactivate it instead.',
        'blocker_children' => 'It has :count sub-categories.',
        'blocker_listings' => 'It has :count listing(s).',
        'blocker_wanted' => 'It has :count wanted ad(s).',
        'blocker_signatures' => 'It has :count signed contract(s) that must be kept.',
        'blocker_enrollments' => 'It has :count seller enrolment(s).',
        'blocker_exclusive' => 'It has :count exclusive contract(s).',
        'blocker_prices' => 'It has :count configured price rule(s).',
        'slug_uuid' => 'The slug must not look like an id.',
        'parent_must_be_top_level' => 'The parent must be a top-level category (two levels are supported).',
        'has_children_cannot_nest' => 'A category that has sub-categories cannot become a sub-category.',
    ],

];
