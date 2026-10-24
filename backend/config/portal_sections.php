<?php

/*
|--------------------------------------------------------------------------
| Portal content section map
|--------------------------------------------------------------------------
|
| Maps every page_key stored in portal_contents (see
| database/seeders/data/portal_content.php) to the sections ("blocks") an
| admin edits, listed in the order they render on the public portal.
|
| Per page:
|   label_en / label_ar  Human label.
|   route                Portal URL path used for screenshots. `{country}` is the
|                        country site_code; `{collection}`, `{article}`, `{category}`,
|                        `{slug}` mean "first published record".
|   scope                'page'   -> content shows on that one page.
|                        'shared' -> content is rendered on many pages (nav, footer, layouts);
|                                    capture it from `route`.
|   sections             Ordered list, one entry per block_key:
|     block              block_key in portal_contents.
|     label_en / label_ar
|     selector           CSS selector that wraps the section on the rendered page
|                        (mostly [data-portal-section="..."] attributes in the Blade
|                        views), or null when the block has no visible DOM
|                        (<title>/meta, legacy rows).
|     route (optional)   Overrides the page route when the block is rendered on a
|                        different portal page (e.g. why-sell on the landing page).
|     requires (optional) UI state needed before capturing (wizard step).
|
| Blocks that exist in the database but are missing here are shown by the admin
| UI under "Other" (appended alphabetically).
*/

return [
    'home' => [
        'label_en' => 'Home (seller landing)',
        'label_ar' => 'الصفحة الرئيسية (للبائعين)',
        'route' => '/vendors',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'fulfillment_teaser', 'label_en' => 'Shipping & fulfilment teaser', 'label_ar' => 'نبذة عن الشحن والتنفيذ', 'selector' => '[data-portal-section="fulfillment_teaser"]'],
            ['block' => 'smart_tools_teaser', 'label_en' => 'Smart tools teaser', 'label_ar' => 'نبذة عن الأدوات الذكية', 'selector' => '[data-portal-section="smart_tools_teaser"]'],
            ['block' => 'smart_tools_teaser_item_1', 'label_en' => 'Smart tools card 1', 'label_ar' => 'بطاقة الأدوات الذكية 1', 'selector' => '[data-portal-section="smart_tools_teaser"] .grid > :nth-child(1)'],
            ['block' => 'smart_tools_teaser_item_2', 'label_en' => 'Smart tools card 2', 'label_ar' => 'بطاقة الأدوات الذكية 2', 'selector' => '[data-portal-section="smart_tools_teaser"] .grid > :nth-child(2)'],
            ['block' => 'smart_tools_teaser_item_3', 'label_en' => 'Smart tools card 3', 'label_ar' => 'بطاقة الأدوات الذكية 3', 'selector' => '[data-portal-section="smart_tools_teaser"] .grid > :nth-child(3)'],
            ['block' => 'testimonials', 'label_en' => 'Testimonials', 'label_ar' => 'آراء العملاء', 'selector' => '[data-portal-section="testimonials"]'],
            ['block' => 'testimonial_1', 'label_en' => 'Testimonial 1', 'label_ar' => 'شهادة 1', 'selector' => '[data-portal-section="testimonials"] .snap-x > :nth-child(1)'],
            ['block' => 'testimonial_2', 'label_en' => 'Testimonial 2', 'label_ar' => 'شهادة 2', 'selector' => '[data-portal-section="testimonials"] .snap-x > :nth-child(2)'],
            ['block' => 'testimonial_3', 'label_en' => 'Testimonial 3', 'label_ar' => 'شهادة 3', 'selector' => '[data-portal-section="testimonials"] .snap-x > :nth-child(3)'],
            ['block' => 'testimonial_4', 'label_en' => 'Testimonial 4', 'label_ar' => 'شهادة 4', 'selector' => '[data-portal-section="testimonials"] .snap-x > :nth-child(4)'],
            ['block' => 'testimonial_5', 'label_en' => 'Testimonial 5', 'label_ar' => 'شهادة 5', 'selector' => '[data-portal-section="testimonials"] .snap-x > :nth-child(5)'],
            ['block' => 'help', 'label_en' => 'Need help?', 'label_ar' => 'هل تحتاج للمساعدة؟', 'selector' => '[data-portal-section="help"]'],
        ],
    ],

    'nav' => [
        'label_en' => 'Navigation bar',
        'label_ar' => 'شريط التنقل',
        'route' => '/vendors',
        'scope' => 'shared',
        'sections' => [
            ['block' => 'logo', 'label_en' => 'Logo', 'label_ar' => 'الشعار', 'selector' => '[data-portal-section="nav-logo"]'],
            ['block' => 'link_home', 'label_en' => 'Link: Home', 'label_ar' => 'رابط: الرئيسية', 'selector' => '[data-portal-section="nav-links"] > div:nth-child(1)'],
            ['block' => 'link_how_it_works', 'label_en' => 'Link: How it works', 'label_ar' => 'رابط: كيف يعمل', 'selector' => '[data-portal-section="nav-links"] > div:nth-child(2)'],
            ['block' => 'link_fulfillment', 'label_en' => 'Link: Fulfilment', 'label_ar' => 'رابط: التنفيذ', 'selector' => '[data-portal-section="nav-links"] > div:nth-child(3)'],
            ['block' => 'link_smart_tools', 'label_en' => 'Link: Smart tools', 'label_ar' => 'رابط: الأدوات الذكية', 'selector' => '[data-portal-section="nav-links"] > div:nth-child(4)'],
            ['block' => 'link_blog', 'label_en' => 'Link: Blog', 'label_ar' => 'رابط: المدونة', 'selector' => '[data-portal-section="nav-links"] > div:nth-child(5)'],
            ['block' => 'link_join', 'label_en' => 'Link: Join us', 'label_ar' => 'رابط: انضم إلينا', 'selector' => '[data-portal-section="nav-links"] > div:nth-child(6)'],
            ['block' => 'country_toggle', 'label_en' => 'Country selector', 'label_ar' => 'اختيار الدولة', 'selector' => '[data-portal-section="nav-controls"]'],
            ['block' => 'language_toggle', 'label_en' => 'Language toggle', 'label_ar' => 'تبديل اللغة', 'selector' => '[data-portal-section="nav-controls"]'],
            ['block' => 'lang_option_en', 'label_en' => 'Language option: English', 'label_ar' => 'خيار اللغة: الإنجليزية', 'selector' => '[data-portal-section="nav-controls"]'],
            ['block' => 'lang_option_ar', 'label_en' => 'Language option: Arabic', 'label_ar' => 'خيار اللغة: العربية', 'selector' => '[data-portal-section="nav-controls"]'],
            ['block' => 'mobile', 'label_en' => 'Mobile menu', 'label_ar' => 'قائمة الجوال', 'selector' => '[data-portal-section="nav"]'],
            ['block' => 'advertise_nav', 'label_en' => 'Advertise navigation bar', 'label_ar' => 'شريط تنقل الإعلانات', 'selector' => '[data-portal-section="advertise_nav"]', 'route' => '/{country}/advertise/sellers'],
        ],
    ],

    'layout' => [
        'label_en' => 'Page layouts (title & description)',
        'label_ar' => 'قوالب الصفحات (العنوان والوصف)',
        'route' => '/vendors',
        'scope' => 'shared',
        'sections' => [
            ['block' => 'portal', 'label_en' => 'Portal layout', 'label_ar' => 'قالب البوابة', 'selector' => null],
            ['block' => 'advertise', 'label_en' => 'Advertise layout', 'label_ar' => 'قالب الإعلانات', 'selector' => null],
            ['block' => 'adsupport', 'label_en' => 'Ads support layout', 'label_ar' => 'قالب دعم الإعلانات', 'selector' => null],
            ['block' => 'helpcenter', 'label_en' => 'Help center layout', 'label_ar' => 'قالب مركز المساعدة', 'selector' => null],
        ],
    ],

    'cta_footer' => [
        'label_en' => 'Call-to-action footer',
        'label_ar' => 'تذييل الدعوة لاتخاذ إجراء',
        'route' => '/faq',
        'scope' => 'shared',
        'sections' => [
            ['block' => 'main', 'label_en' => 'Sign-up banner & copyright', 'label_ar' => 'لافتة التسجيل وحقوق النشر', 'selector' => '[data-portal-section="cta-footer"]'],
        ],
    ],

    'advertise_footer' => [
        'label_en' => 'Advertise footer',
        'label_ar' => 'تذييل الإعلانات',
        'route' => '/{country}/advertise/sellers',
        'scope' => 'shared',
        'sections' => [
            ['block' => 'main', 'label_en' => 'Footer links & copyright', 'label_ar' => 'روابط التذييل وحقوق النشر', 'selector' => '[data-portal-section="advertise_footer"]'],
        ],
    ],

    'how-it-works' => [
        'label_en' => 'How it works',
        'label_ar' => 'كيف يعمل',
        'route' => '/how-it-works',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'steps', 'label_en' => 'Steps to go live', 'label_ar' => 'خطوات بدء البيع', 'selector' => '[data-portal-section="steps"]'],
            ['block' => 'seller-hub', 'label_en' => 'Seller hub', 'label_ar' => 'مركز البائع', 'selector' => '[data-portal-section="seller-hub"]'],
            ['block' => 'seller-hub-item-1', 'label_en' => 'Seller hub item 1', 'label_ar' => 'عنصر مركز البائع 1', 'selector' => '[data-portal-section="seller-hub"] ul > :nth-child(1)'],
            ['block' => 'seller-hub-item-2', 'label_en' => 'Seller hub item 2', 'label_ar' => 'عنصر مركز البائع 2', 'selector' => '[data-portal-section="seller-hub"] ul > :nth-child(2)'],
            ['block' => 'seller-hub-item-3', 'label_en' => 'Seller hub item 3', 'label_ar' => 'عنصر مركز البائع 3', 'selector' => '[data-portal-section="seller-hub"] ul > :nth-child(3)'],
            ['block' => 'fulfilment-model', 'label_en' => 'Fulfilment model', 'label_ar' => 'نموذج التنفيذ', 'selector' => '[data-portal-section="fulfilment-model"]'],
            ['block' => 'why-sell', 'label_en' => 'Why join us (shown on the landing page)', 'label_ar' => 'لماذا تنضم إلينا (يظهر في الصفحة الرئيسية)', 'selector' => '[data-portal-section="why-sell"]', 'route' => '/vendors'],
            ['block' => 'why-sell-item-1', 'label_en' => 'Why join item 1', 'label_ar' => 'عنصر لماذا تنضم 1', 'selector' => '[data-portal-section="why-sell"] .grid > :nth-child(1)', 'route' => '/vendors'],
            ['block' => 'why-sell-item-2', 'label_en' => 'Why join item 2', 'label_ar' => 'عنصر لماذا تنضم 2', 'selector' => '[data-portal-section="why-sell"] .grid > :nth-child(2)', 'route' => '/vendors'],
            ['block' => 'why-sell-item-3', 'label_en' => 'Why join item 3', 'label_ar' => 'عنصر لماذا تنضم 3', 'selector' => '[data-portal-section="why-sell"] .grid > :nth-child(3)', 'route' => '/vendors'],
            ['block' => 'checklist', 'label_en' => 'Seller setup checklist (shown on the landing page)', 'label_ar' => 'قائمة إعداد البائع (تظهر في الصفحة الرئيسية)', 'selector' => '[data-portal-section="checklist"]', 'route' => '/vendors'],
            ['block' => 'trader_banner', 'label_en' => 'Traders banner (shown on the landing page)', 'label_ar' => 'لافتة التجار (تظهر في الصفحة الرئيسية)', 'selector' => '[data-portal-section="trader_banner"]', 'route' => '/vendors'],
        ],
    ],

    'account_setup' => [
        'label_en' => 'Account setup steps',
        'label_ar' => 'خطوات إعداد الحساب',
        'route' => '/how-it-works',
        'scope' => 'page',
        'sections' => [
            ['block' => 'main', 'label_en' => 'Account setup section', 'label_ar' => 'قسم إعداد الحساب', 'selector' => '[data-portal-section="account-setup"]'],
            ['block' => 'step_1', 'label_en' => 'Account step 1', 'label_ar' => 'خطوة الحساب 1', 'selector' => '[data-portal-section="account-setup"] [x-data] > :nth-child(1)'],
            ['block' => 'step_2', 'label_en' => 'Account step 2', 'label_ar' => 'خطوة الحساب 2', 'selector' => '[data-portal-section="account-setup"] [x-data] > :nth-child(2)'],
            ['block' => 'step_3', 'label_en' => 'Account step 3', 'label_ar' => 'خطوة الحساب 3', 'selector' => '[data-portal-section="account-setup"] [x-data] > :nth-child(3)'],
        ],
    ],

    'advertise-product' => [
        'label_en' => 'Advertise: Product ads',
        'label_ar' => 'الإعلان: إعلانات المنتجات',
        'route' => '/{country}/advertise/product',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'hero_feature_1', 'label_en' => 'Hero feature 1', 'label_ar' => 'ميزة القسم الرئيسي 1', 'selector' => '[data-portal-section="hero"] .items-stretch > :nth-child(1)'],
            ['block' => 'hero_feature_2', 'label_en' => 'Hero feature 2', 'label_ar' => 'ميزة القسم الرئيسي 2', 'selector' => '[data-portal-section="hero"] .items-stretch > :nth-child(2)'],
            ['block' => 'hero_feature_3', 'label_en' => 'Hero feature 3', 'label_ar' => 'ميزة القسم الرئيسي 3', 'selector' => '[data-portal-section="hero"] .items-stretch > :nth-child(3)'],
            ['block' => 'quick_guide', 'label_en' => 'Quick guide', 'label_ar' => 'دليل سريع', 'selector' => '[data-portal-section="quick_guide"]'],
            ['block' => 'faq', 'label_en' => 'FAQ', 'label_ar' => 'الأسئلة الشائعة', 'selector' => '[data-portal-section="faq"]'],
            ['block' => 'listings', 'label_en' => 'Product listings guide (shown on How it works)', 'label_ar' => 'دليل عرض المنتجات (يظهر في كيف يعمل)', 'selector' => '[data-portal-section="listings"]', 'route' => '/how-it-works'],
        ],
    ],

    'advertise-display' => [
        'label_en' => 'Advertise: Display ads',
        'label_ar' => 'الإعلان: الإعلانات المعروضة',
        'route' => '/{country}/advertise/display',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'hero_feature_1', 'label_en' => 'Hero feature 1', 'label_ar' => 'ميزة القسم الرئيسي 1', 'selector' => '[data-portal-section="hero"] .items-stretch > :nth-child(1)'],
            ['block' => 'hero_feature_2', 'label_en' => 'Hero feature 2', 'label_ar' => 'ميزة القسم الرئيسي 2', 'selector' => '[data-portal-section="hero"] .items-stretch > :nth-child(2)'],
            ['block' => 'hero_feature_3', 'label_en' => 'Hero feature 3', 'label_ar' => 'ميزة القسم الرئيسي 3', 'selector' => '[data-portal-section="hero"] .items-stretch > :nth-child(3)'],
            ['block' => 'quick_guide', 'label_en' => 'Quick guide', 'label_ar' => 'دليل سريع', 'selector' => '[data-portal-section="quick_guide"]'],
            ['block' => 'faq', 'label_en' => 'FAQ', 'label_ar' => 'الأسئلة الشائعة', 'selector' => '[data-portal-section="faq"]'],
        ],
    ],

    'advertise-brands' => [
        'label_en' => 'Advertise: Brands',
        'label_ar' => 'الإعلان: العلامات التجارية',
        'route' => '/{country}/advertise/brands',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'solutions_item_1', 'label_en' => 'Solution 1', 'label_ar' => 'حل 1', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(1)'],
            ['block' => 'solutions_item_2', 'label_en' => 'Solution 2', 'label_ar' => 'حل 2', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(2)'],
            ['block' => 'solutions_item_3', 'label_en' => 'Solution 3', 'label_ar' => 'حل 3', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(3)'],
            ['block' => 'solutions_item_4', 'label_en' => 'Solution 4', 'label_ar' => 'حل 4', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(4)'],
            ['block' => 'solutions_item_5', 'label_en' => 'Solution 5', 'label_ar' => 'حل 5', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(5)'],
            ['block' => 'solutions_item_6', 'label_en' => 'Solution 6', 'label_ar' => 'حل 6', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(6)'],
            ['block' => 'testimonials', 'label_en' => 'Testimonials', 'label_ar' => 'آراء العملاء', 'selector' => '[data-portal-section="testimonials"]'],
            ['block' => 'testimonial_1', 'label_en' => 'Testimonial 1', 'label_ar' => 'شهادة 1', 'selector' => '[data-portal-section="testimonials"] .transition-transform > :nth-child(1)'],
            ['block' => 'testimonial_2', 'label_en' => 'Testimonial 2', 'label_ar' => 'شهادة 2', 'selector' => '[data-portal-section="testimonials"] .transition-transform > :nth-child(2)'],
            ['block' => 'testimonial_3', 'label_en' => 'Testimonial 3', 'label_ar' => 'شهادة 3', 'selector' => '[data-portal-section="testimonials"] .transition-transform > :nth-child(3)'],
        ],
    ],

    'advertise-advertisers' => [
        'label_en' => 'Advertise: Advertisers',
        'label_ar' => 'الإعلان: المعلنون',
        'route' => '/{country}/advertise/advertisers',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'solutions_item_1', 'label_en' => 'Solution 1', 'label_ar' => 'حل 1', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(1)'],
            ['block' => 'solutions_item_2', 'label_en' => 'Solution 2', 'label_ar' => 'حل 2', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(2)'],
            ['block' => 'solutions_item_3', 'label_en' => 'Solution 3', 'label_ar' => 'حل 3', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(3)'],
            ['block' => 'solutions_item_4', 'label_en' => 'Solution 4', 'label_ar' => 'حل 4', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(4)'],
        ],
    ],

    'advertise-request' => [
        'label_en' => 'Advertise: Contact form',
        'label_ar' => 'الإعلان: نموذج التواصل',
        'route' => '/{country}/advertise/request',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'form', 'label_en' => 'Contact form', 'label_ar' => 'نموذج التواصل', 'selector' => '[data-portal-section="form"]'],
        ],
    ],

    'sellers' => [
        'label_en' => 'Advertise: Sellers',
        'label_ar' => 'الإعلان: البائعون',
        'route' => '/{country}/advertise/sellers',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'solution_product_ads', 'label_en' => 'Solution 1', 'label_ar' => 'حل 1', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(1)'],
            ['block' => 'solution_display_ads', 'label_en' => 'Solution 2', 'label_ar' => 'حل 2', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(2)'],
            ['block' => 'solution_crm_social', 'label_en' => 'Solution 3', 'label_ar' => 'حل 3', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(3)'],
            ['block' => 'solution_brand_ads', 'label_en' => 'Solution 4', 'label_ar' => 'حل 4', 'selector' => '[data-portal-section="solutions"] .grid > :nth-child(4)'],
            ['block' => 'testimonials', 'label_en' => 'Testimonials', 'label_ar' => 'آراء العملاء', 'selector' => '[data-portal-section="testimonials"]'],
            ['block' => 'testimonial_1', 'label_en' => 'Testimonial 1', 'label_ar' => 'شهادة 1', 'selector' => '[data-portal-section="testimonials"] .transition-transform > :nth-child(1)'],
            ['block' => 'testimonial_2', 'label_en' => 'Testimonial 2', 'label_ar' => 'شهادة 2', 'selector' => '[data-portal-section="testimonials"] .transition-transform > :nth-child(2)'],
            ['block' => 'testimonial_3', 'label_en' => 'Testimonial 3', 'label_ar' => 'شهادة 3', 'selector' => '[data-portal-section="testimonials"] .transition-transform > :nth-child(3)'],
        ],
    ],

    'smart-tools' => [
        'label_en' => 'Smart tools',
        'label_ar' => 'الأدوات الذكية',
        'route' => '/smart-tools',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'ads', 'label_en' => 'Advertising on Nawy', 'label_ar' => 'الإعلان على ناوي', 'selector' => '[data-portal-section="ads"]'],
            ['block' => 'fee-structure', 'label_en' => 'Fee structure', 'label_ar' => 'هيكل الرسوم', 'selector' => '[data-portal-section="fee-structure"]'],
            ['block' => 'transparent-fees', 'label_en' => 'Transparent fees', 'label_ar' => 'رسوم شفافة', 'selector' => '[data-portal-section="transparent-fees"]'],
            ['block' => 'fee-card-referral', 'label_en' => 'Fee card 1', 'label_ar' => 'بطاقة رسوم 1', 'selector' => '[data-portal-section="transparent-fees"] .grid > :nth-child(1)'],
            ['block' => 'fee-card-fulfilment', 'label_en' => 'Fee card 2', 'label_ar' => 'بطاقة رسوم 2', 'selector' => '[data-portal-section="transparent-fees"] .grid > :nth-child(2)'],
            ['block' => 'fee-card-other', 'label_en' => 'Fee card 3', 'label_ar' => 'بطاقة رسوم 3', 'selector' => '[data-portal-section="transparent-fees"] .grid > :nth-child(3)'],
            ['block' => 'payouts', 'label_en' => 'Payouts', 'label_ar' => 'المدفوعات', 'selector' => '[data-portal-section="payouts"]'],
            ['block' => 'insights', 'label_en' => 'Insights', 'label_ar' => 'الرؤى والتحليلات', 'selector' => '[data-portal-section="insights"]'],
            ['block' => 'vantage', 'label_en' => 'Vantage analytics', 'label_ar' => 'تحليلات فانتج', 'selector' => '[data-portal-section="vantage"]'],
        ],
    ],

    'fulfillment' => [
        'label_en' => 'Fulfilment',
        'label_ar' => 'التنفيذ',
        'route' => '/fulfillment',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'fbn', 'label_en' => 'Fulfilled by Nawy (FBN)', 'label_ar' => 'التنفيذ من ناوي', 'selector' => '[data-portal-section="fbn"]'],
            ['block' => 'fbn_testimonial', 'label_en' => 'FBN testimonial', 'label_ar' => 'شهادة التنفيذ من ناوي', 'selector' => '[data-portal-section="fbn_testimonial"]'],
            ['block' => 'fbp', 'label_en' => 'Fulfilled by Partner (FBP)', 'label_ar' => 'التنفيذ من الشريك', 'selector' => '[data-portal-section="fbp"]'],
            ['block' => 'fbp_testimonial', 'label_en' => 'FBP testimonial', 'label_ar' => 'شهادة التنفيذ من الشريك', 'selector' => '[data-portal-section="fbp_testimonial"]'],
        ],
    ],

    'faq' => [
        'label_en' => 'FAQ',
        'label_ar' => 'الأسئلة الشائعة',
        'route' => '/faq',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'header', 'label_en' => 'Header & questions', 'label_ar' => 'الترويسة والأسئلة', 'selector' => '[data-portal-section="faq-header"]'],
            ['block' => 'contact_cta', 'label_en' => 'Contact support call-to-action', 'label_ar' => 'دعوة التواصل مع الدعم', 'selector' => '[data-portal-section="contact_cta"]'],
        ],
    ],

    'join' => [
        'label_en' => 'Join',
        'label_ar' => 'الانضمام',
        'route' => '/join',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'logo', 'label_en' => 'Logo', 'label_ar' => 'الشعار', 'selector' => '.choice-header a'],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '.choice-hero'],
            ['block' => 'card_product_vendor', 'label_en' => 'Card: Product seller', 'label_ar' => 'بطاقة: بائع منتجات', 'selector' => '.choice-cards > :nth-child(1)'],
            ['block' => 'card_classified_vendor', 'label_en' => 'Card: Companies & brokers', 'label_ar' => 'بطاقة: الشركات والوسطاء', 'selector' => '.choice-cards > :nth-child(2)'],
            ['block' => 'card_marketer', 'label_en' => 'Card: Marketers', 'label_ar' => 'بطاقة: المسوقون', 'selector' => '.choice-cards > :nth-child(3)'],
            ['block' => 'actions', 'label_en' => 'Continue button & hint', 'label_ar' => 'زر المتابعة والتلميح', 'selector' => '.choice-actions'],
        ],
    ],

    'companies' => [
        'label_en' => 'Companies & brokers',
        'label_ar' => 'الشركات والوسطاء',
        'route' => '/companies',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '.choice-hero'],
            ['block' => 'card_travel_agency', 'label_en' => 'Card: Travel agency', 'label_ar' => 'بطاقة: وكالة سفر', 'selector' => '.choice-cards > :nth-child(1)'],
            ['block' => 'card_open_souq', 'label_en' => 'Card: Open Souq', 'label_ar' => 'بطاقة: السوق المفتوح', 'selector' => '.choice-cards > :nth-child(2)'],
            ['block' => 'form', 'label_en' => 'Specialization picker', 'label_ar' => 'اختيار التخصص', 'selector' => '.choice-reveal'],
            ['block' => 'actions', 'label_en' => 'Continue button & hint', 'label_ar' => 'زر المتابعة والتلميح', 'selector' => '.choice-actions'],
        ],
    ],

    'marketers' => [
        'label_en' => 'Marketers',
        'label_ar' => 'المسوقون',
        'route' => '/marketers',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'card_influencer', 'label_en' => 'Card: Influencer', 'label_ar' => 'بطاقة: مشهور / مؤثر', 'selector' => '[data-portal-section="hero"]', 'route' => '/marketers/influencer'],
            ['block' => 'card_affiliate', 'label_en' => 'Card: Affiliate', 'label_ar' => 'بطاقة: مسوق بالعمولة', 'selector' => '[data-portal-section="hero"]', 'route' => '/marketers/affiliate'],
            ['block' => 'card_broker', 'label_en' => 'Card: Broker', 'label_ar' => 'بطاقة: وسيط', 'selector' => '[data-portal-section="hero"]', 'route' => '/marketers/broker'],
            ['block' => 'actions', 'label_en' => 'Continue button & hint (legacy, not rendered)', 'label_ar' => 'زر المتابعة والتلميح (قديم، غير معروض)', 'selector' => null],
        ],
    ],

    'open-souq' => [
        'label_en' => 'Open Souq',
        'label_ar' => 'السوق المفتوح',
        'route' => '/companies/open-souq',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'benefits', 'label_en' => 'Benefits', 'label_ar' => 'المزايا', 'selector' => '[data-portal-section="benefits"]'],
            ['block' => 'benefits_item_1', 'label_en' => 'Benefit 1', 'label_ar' => 'ميزة 1', 'selector' => '[data-portal-section="benefits"] .grid > :nth-child(1)'],
            ['block' => 'benefits_item_2', 'label_en' => 'Benefit 2', 'label_ar' => 'ميزة 2', 'selector' => '[data-portal-section="benefits"] .grid > :nth-child(2)'],
            ['block' => 'benefits_item_3', 'label_en' => 'Benefit 3', 'label_ar' => 'ميزة 3', 'selector' => '[data-portal-section="benefits"] .grid > :nth-child(3)'],
            ['block' => 'categories', 'label_en' => 'What you can list', 'label_ar' => 'ما يمكنك إدراجه', 'selector' => '[data-portal-section="categories"]'],
            ['block' => 'steps', 'label_en' => 'Getting started steps', 'label_ar' => 'خطوات البدء', 'selector' => '[data-portal-section="steps"]'],
            ['block' => 'cta', 'label_en' => 'Closing call-to-action', 'label_ar' => 'دعوة ختامية', 'selector' => '[data-portal-section="cta"]'],
        ],
    ],

    'travel-agency' => [
        'label_en' => 'Travel agency',
        'label_ar' => 'وكالة السفر',
        'route' => '/companies/travel-agency',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'القسم الرئيسي', 'selector' => '[data-portal-section="hero"]'],
            ['block' => 'benefits', 'label_en' => 'Benefits', 'label_ar' => 'المزايا', 'selector' => '[data-portal-section="benefits"]'],
            ['block' => 'benefits_item_1', 'label_en' => 'Benefit 1', 'label_ar' => 'ميزة 1', 'selector' => '[data-portal-section="benefits"] .grid > :nth-child(1)'],
            ['block' => 'benefits_item_2', 'label_en' => 'Benefit 2', 'label_ar' => 'ميزة 2', 'selector' => '[data-portal-section="benefits"] .grid > :nth-child(2)'],
            ['block' => 'benefits_item_3', 'label_en' => 'Benefit 3', 'label_ar' => 'ميزة 3', 'selector' => '[data-portal-section="benefits"] .grid > :nth-child(3)'],
            ['block' => 'categories', 'label_en' => 'What you can publish', 'label_ar' => 'ما يمكنك نشره', 'selector' => '[data-portal-section="categories"]'],
            ['block' => 'steps', 'label_en' => 'Getting started steps', 'label_ar' => 'خطوات البدء', 'selector' => '[data-portal-section="steps"]'],
            ['block' => 'cta', 'label_en' => 'Closing call-to-action', 'label_ar' => 'دعوة ختامية', 'selector' => '[data-portal-section="cta"]'],
            ['block' => 'register', 'label_en' => 'Registration wizard', 'label_ar' => 'معالج التسجيل', 'selector' => '[data-portal-section="register"]', 'route' => '/companies/travel-agency/register'],
            ['block' => 'success', 'label_en' => 'Registration success', 'label_ar' => 'نجاح التسجيل', 'selector' => '[data-portal-section="success"]', 'route' => '/companies/travel-agency/register/success'],
        ],
    ],

    'register' => [
        'label_en' => 'Seller registration',
        'label_ar' => 'تسجيل البائع',
        'route' => '/vendors/register',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'header', 'label_en' => 'Header', 'label_ar' => 'الترويسة', 'selector' => '[data-portal-section="header"]'],
            ['block' => 'progress', 'label_en' => 'Progress bar', 'label_ar' => 'شريط التقدم', 'selector' => '[data-portal-section="progress"]'],
            ['block' => 'step_1', 'label_en' => 'Step 1', 'label_ar' => 'الخطوة 1', 'selector' => '[data-portal-section="step_1"]', 'requires' => 'wizard step 1'],
            ['block' => 'step_2', 'label_en' => 'Step 2', 'label_ar' => 'الخطوة 2', 'selector' => '[data-portal-section="step_2"]', 'requires' => 'wizard step 2'],
            ['block' => 'step_3', 'label_en' => 'Step 3', 'label_ar' => 'الخطوة 3', 'selector' => '[data-portal-section="step_3"]', 'requires' => 'wizard step 3'],
            ['block' => 'step_4', 'label_en' => 'Step 4', 'label_ar' => 'الخطوة 4', 'selector' => '[data-portal-section="step_4"]', 'requires' => 'wizard step 4'],
            ['block' => 'step_5', 'label_en' => 'Step 5', 'label_ar' => 'الخطوة 5', 'selector' => '[data-portal-section="step_5"]', 'requires' => 'wizard step 5'],
            ['block' => 'footer_nav', 'label_en' => 'Wizard navigation buttons', 'label_ar' => 'أزرار التنقل في المعالج', 'selector' => '[data-portal-section="footer_nav"]'],
            ['block' => 'login_prompt', 'label_en' => 'Log-in prompt', 'label_ar' => 'رابط تسجيل الدخول', 'selector' => '[data-portal-section="login_prompt"]'],
            ['block' => 'success', 'label_en' => 'Registration success', 'label_ar' => 'نجاح التسجيل', 'selector' => '[data-portal-section="success"]', 'route' => '/vendors/register/success'],
        ],
    ],

    'blog' => [
        'label_en' => 'Blog',
        'label_ar' => 'المدونة',
        'route' => '/blog',
        'scope' => 'page',
        'sections' => [
            ['block' => 'meta', 'label_en' => 'Page title & SEO', 'label_ar' => 'عنوان الصفحة والـ SEO', 'selector' => null],
            ['block' => 'header', 'label_en' => 'Header & search', 'label_ar' => 'الترويسة والبحث', 'selector' => '[data-portal-section="header"]'],
            ['block' => 'index', 'label_en' => 'Posts grid', 'label_ar' => 'شبكة المقالات', 'selector' => '[data-portal-section="index"]'],
            ['block' => 'show', 'label_en' => 'Post page (article)', 'label_ar' => 'صفحة المقال', 'selector' => '[data-portal-section="show"]', 'route' => '/blog/{slug}'],
        ],
    ],

    'adsupport' => [
        'label_en' => 'Ads support (Knowledge Hub)',
        'label_ar' => 'دعم الإعلانات (مركز المعرفة)',
        'route' => '/{country}/adsupport',
        'scope' => 'page',
        'sections' => [
            ['block' => 'header', 'label_en' => 'Header & search', 'label_ar' => 'الترويسة والبحث', 'selector' => '[data-portal-section="header"]'],
            ['block' => 'index', 'label_en' => 'Collections home', 'label_ar' => 'الصفحة الرئيسية للمجموعات', 'selector' => '[data-portal-section="index"]'],
            ['block' => 'collection', 'label_en' => 'Collection page', 'label_ar' => 'صفحة المجموعة', 'selector' => '[data-portal-section="collection"]', 'route' => '/{country}/adsupport/collections/{collection}'],
            ['block' => 'article', 'label_en' => 'Article page', 'label_ar' => 'صفحة المقال', 'selector' => '[data-portal-section="article"]', 'route' => '/{country}/adsupport/articles/{article}'],
            ['block' => 'common', 'label_en' => 'Breadcrumb', 'label_ar' => 'مسار التنقل', 'selector' => '[data-portal-section="common"]', 'route' => '/{country}/adsupport/collections/{collection}'],
            ['block' => 'footer', 'label_en' => 'Footer', 'label_ar' => 'التذييل', 'selector' => '[data-portal-section="footer"]'],
        ],
    ],

    'helpcenter' => [
        'label_en' => 'Seller help center',
        'label_ar' => 'مركز مساعدة البائع',
        'route' => '/{country}/helpcenter',
        'scope' => 'page',
        'sections' => [
            ['block' => 'header', 'label_en' => 'Header & search', 'label_ar' => 'الترويسة والبحث', 'selector' => '[data-portal-section="header"]'],
            ['block' => 'index', 'label_en' => 'Categories home', 'label_ar' => 'الصفحة الرئيسية للفئات', 'selector' => '[data-portal-section="index"]'],
            ['block' => 'category', 'label_en' => 'Category page', 'label_ar' => 'صفحة الفئة', 'selector' => '[data-portal-section="category"]', 'route' => '/{country}/helpcenter/category/{category}'],
            ['block' => 'article', 'label_en' => 'Article page', 'label_ar' => 'صفحة المقال', 'selector' => '[data-portal-section="article"]', 'route' => '/{country}/helpcenter/article/{article}'],
            ['block' => 'search', 'label_en' => 'Search results', 'label_ar' => 'نتائج البحث', 'selector' => '[data-portal-section="search"]', 'route' => '/{country}/helpcenter/search?q=a'],
            ['block' => 'common', 'label_en' => 'Breadcrumb', 'label_ar' => 'مسار التنقل', 'selector' => '[data-portal-section="common"]', 'route' => '/{country}/helpcenter/category/{category}'],
            ['block' => 'footer', 'label_en' => 'Footer', 'label_ar' => 'التذييل', 'selector' => '[data-portal-section="footer"]'],
        ],
    ],

];
