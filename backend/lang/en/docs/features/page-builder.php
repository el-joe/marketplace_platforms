<?php

return [
    'title' => 'Page Builder',

    'what_it_is' => [
        'heading' => '1. What It Is',
        'body1' => 'A drag-and-drop visual builder for storefront pages. Admin creates pages, adds blocks, configures each block\'s content, and publishes.',
        'body2' => 'Revisions are tracked — any block or page version can be restored. Blocks are append-only models (PageBlockRevision, PageRevision) — updates create new revisions.',
    ],

    'how_it_works' => [
        'heading' => '2. How It Works',
        'open' => 'Admin opens /admin/page-builder',
        'select' => 'Selects or creates a page (home, category landing, promo page, etc.)',
        'add_blocks' => 'Adds blocks to the page (each block has a type)',
        'configure' => 'Configures each block via the config panel (slides, products, categories, etc.)',
        'publish' => 'Publishes the page → storefront immediately reflects changes',
    ],

    'block_types' => [
        'heading' => '3. Block Types and What They Do',
        'block_type' => 'Block Type',
        'purpose' => 'Purpose',
        'content_sources' => 'Content Sources',
        'hero_slider_purpose' => 'Full-width image carousel at top of page',
        'hero_slider_sources' => 'Slides with image, title, CTA URL',
        'ad_images_purpose' => 'Grid of promotional banner images',
        'ad_images_sources' => 'Ad images with links',
        'category_pills_purpose' => 'Horizontal scrollable category chips',
        'category_pills_sources' => 'Categories from catalog',
        'brand_strip_purpose' => 'Horizontal brand logo row',
        'brand_strip_sources' => 'Brands/Sellers',
        'product_grid_purpose' => 'Manual product selection grid',
        'product_grid_sources' => 'Products (manually picked)',
        'product_carousel_purpose' => 'Auto or manual product carousel',
        'product_carousel_sources' => 'Products or dynamic source',
        'flash_sale_timer_purpose' => 'Countdown clock tied to a flash sale event',
        'flash_sale_timer_sources' => 'Links to flash_sale',
        'featured_vendor_purpose' => 'Vendor spotlight block',
        'featured_vendor_sources' => 'Single vendor',
        'custom_html_purpose' => 'Raw HTML block for one-offs',
        'custom_html_sources' => 'Inline HTML',
        'banner_single_purpose' => 'Single full-width promo banner',
        'banner_single_sources' => 'Banner image + URL',
    ],

    'slides' => [
        'heading' => '4. Slides Management',
        'body1' => 'Each hero_slider block has slides. Admin can add, reorder (drag), and delete slides.',
        'body2' => 'Each slide: image (FilePond upload), title_en, title_ar, subtitle, CTA URL, CTA text.',
    ],

    'ad_images' => [
        'heading' => '5. Ad Images',
        'body' => "Each ad_images block has ad images. Used for promotional grid (like Noon's category banners). Each: image, click URL, alt text, sort order.",
    ],

    'product_pickers' => [
        'heading' => '6. Product Pickers',
        'body' => 'product_grid and product_carousel blocks let admin search and pin specific products. Search by name/SKU → add to block → reorder via drag.',
    ],

    'revisions' => [
        'heading' => '7. Revisions & Restore',
        'body' => 'Every block config save creates a PageBlockRevision row. Every page publish creates a PageRevision row.',
        'note' => 'Both are APPEND-ONLY — no updates or deletes on revision models. Admin can view revision history and restore any prior version.',
    ],

    'visibility' => [
        'heading' => '8. Block Visibility',
        'body' => 'Each block can be toggled visible/hidden without deleting it. Useful for seasonal content (e.g. hide Ramadan block after Eid).',
    ],

    'analytics' => [
        'heading' => '9. Block Analytics',
        'body' => 'Impressions, clicks, and CTR per block (if tracking is wired in storefront JS).',
    ],
];
