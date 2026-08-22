<?php

return [
    'title' => 'Content Pages',
    'breadcrumb' => 'Features',

    'what_it_is' => [
        'heading' => 'What It Is',
        'body_prefix' => "Everything in the admin sidebar's",
        'content_group' => 'Content',
        'body_suffix' => 'group: page builder, storefront/app configuration, reviews moderation, the blog, the vendor knowledge hub, FAQs, static portal pages, dynamic content settings, and radio channels.',
    ],

    'pages' => [
        'heading' => 'Pages (Page Builder)',
        'see' => 'See the',
        'page_builder_link' => 'Page Builder',
        'documentation' => 'documentation.',
    ],

    'app_contexts' => [
        'heading' => 'App Contexts',
        'body' => 'configures the top strip on the mobile app.',
        'contexts' => 'Contexts: Main Marketplace, Super Mall, Food, 15-Minute Delivery, etc.',
        'each_context' => 'Each context: assign countries, configure bottom navigation items',
    ],

    'reviews' => [
        'heading' => 'Reviews',
        'body' => 'all product reviews submitted by customers.',
        'moderation' => 'Moderation: approve, reject, delete',
        'vendor_reply' => 'Vendor reply management: show/hide vendor responses',
        'bulk_actions' => 'Bulk actions: approve or reject multiple at once',
    ],

    'blog' => [
        'heading' => 'Blog Categories + Blog Posts',
        'body' => 'Vendor- and customer-facing blog.',
        'categories' => 'nested, drag-to-reorder',
        'posts' => 'bilingual (EN/AR), Summernote editor, tags, featured flag, archive',
        'attachments' => 'Attachments: PDFs and files per post (FilePond uploads)',
        'view_counter' => 'View counter: public endpoint increments views, throttled 60/min',
    ],

    'knowledge_hub' => [
        'heading' => 'Knowledge Hub (Collections + Articles)',
        'collections' => 'topic collections for the vendor knowledge base',
        'articles' => 'articles within each collection',
        'pin' => 'Feature: pin important articles to the top',
    ],

    'faqs' => [
        'heading' => 'FAQs',
        'body' => 'public-facing FAQ entries. Reorder via drag, toggle active, bilingual.',
    ],

    'portal' => [
        'heading' => 'Portal Content',
        'body' => 'static page content editor.',
        'pages' => 'Pages: About, Terms of Service, Privacy Policy, Return Policy, Vendor Agreement, etc.',
        'editor' => 'Summernote editor for each language',
    ],

    'settings' => [
        'heading' => 'Content Settings',
        'body' => 'all dynamic media, text, and URLs across the platform.',
        'types' => '12 setting types: text, textarea, editor, file, url, email, phone, number, boolean, color, select, json',
        'groups' => 'Groups: general, appearance, homepage, footer, auth_pages, vendor_portal, emails, seo, policies, notifications',
        'cache' => 'Changes apply instantly via View Composer cache (5-minute TTL, cleared on save)',
    ],

    'radio' => [
        'heading' => 'Radio Channels',
        'body_prefix' => 'Online radio stations embedded in the storefront',
        'see' => 'see the',
        'link' => 'Radio Channels',
        'doc' => 'doc for details.',
        'customer_route' => 'Customer route',
    ],

    'rules' => [
        'heading' => 'Who Uses It & Key Rules',
        'admin_label' => 'Admin',
        'admin_owns' => 'owns all content entries; nothing here is editable by vendors or customers',
        'bilingual' => 'All bilingual content requires both EN and AR before it can be marked active in most sections',
        'cache_note' => 'Content settings changes are cached (5 min) and cleared automatically on save &mdash; no manual cache-busting needed',
    ],
];
