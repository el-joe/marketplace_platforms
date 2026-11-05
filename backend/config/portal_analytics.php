<?php

/*
|--------------------------------------------------------------------------
| Portal analytics (visitor tracking + registration funnel)
|--------------------------------------------------------------------------
|
| The registration flows and their steps live in App\Enums\RegistrationFlow;
| this file only holds tunables.
*/

return [

    // Master switch. When false no cookie is set and nothing is recorded.
    'enabled' => env('PORTAL_ANALYTICS_ENABLED', true),

    // Honour the browser's Do-Not-Track header (no cookie, no tracking).
    'honor_dnt' => env('PORTAL_ANALYTICS_HONOR_DNT', true),

    // Anonymous visitor identifier cookie (random UUID, HttpOnly, SameSite=Lax, no personal data).
    'cookie' => [
        'name' => 'portal_vid',
        'minutes' => 60 * 24 * 365,
    ],

    // A visitor idle for longer than this starts a new "visit". Also the window in which
    // re-requesting the page the visitor is already on (a refresh) is not counted again.
    'visit_gap_minutes' => (int) env('PORTAL_ANALYTICS_VISIT_GAP_MINUTES', 30),

    // Routes that never produce a page view (helpers and redirects).
    'excluded_route_names' => [
        'portal.language',
        'portal.country',
        'portal.join.options',
        'portal.register.check-slug',
        'portal.register.cities',
        'portal.register.document-requirements',
        'portal.register.track',
        'portal.blog.posts.increment-views',
    ],

    // Page views older than this many days are deleted by `portal-analytics:prune`.
    // Visitor rows are removed once unseen for as long and not linked to a registration.
    // Registration progress and step events are tiny and are kept.
    'retention_days' => (int) env('PORTAL_ANALYTICS_RETENTION_DAYS', 180),

    // User-agent fragments treated as bots/crawlers (case-insensitive). Empty UA is a bot too.
    'bot_user_agents' => [
        'bot', 'crawl', 'spider', 'slurp', 'bingpreview', 'facebookexternalhit', 'facebot',
        'headless', 'phantomjs', 'lighthouse', 'pingdom', 'uptime', 'monitor', 'preview',
        'curl/', 'wget', 'python-requests', 'python-urllib', 'go-http-client', 'okhttp',
        'java/', 'libwww', 'httpclient', 'axios/', 'node-fetch', 'postman', 'scrapy',
        'semrush', 'ahrefs', 'mj12', 'dotbot', 'petalbot', 'yandex', 'baidu', 'embedly',
        'whatsapp', 'telegrambot', 'discordbot', 'slackbot', 'twitterbot', 'linkedinbot',
    ],

    // Largest report range (days) the dashboard will compute.
    'max_range_days' => 731,
];
