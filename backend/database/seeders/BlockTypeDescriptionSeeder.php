<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills in description + config_schema/default_config for all block_types
 * rows. Idempotent: keyed by `code` via updateOrCreate-style logic, safe to
 * run on a fresh or partially-seeded database.
 */
class BlockTypeDescriptionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $types = [
            [
                'code' => 'hero_slider', 'group' => 'hero',
                'label_en' => 'Hero Slider', 'label_ar' => 'عارض الشرائح',
                'icon' => 'squares-2x2', 'max_per_page' => 1,
                'description_en' => "A full-width image/video carousel at the top of the page. Each slide has its own desktop and mobile image, optional headline, subtitle, and a CTA button that can link to any product, category, or URL. Configure autoplay speed, navigation arrows, and dots under Settings. Add/reorder slides in the Slides panel.",
                'description_ar' => "معرض صور/فيديو بعرض الصفحة الكامل أعلى الصفحة. لكل شريحة صورة خاصة بها لسطح المكتب والجوال، وعنوان رئيسي اختياري، وعنوان فرعي، وزر دعوة لاتخاذ إجراء يمكن ربطه بأي منتج أو فئة أو رابط. يمكن ضبط سرعة التشغيل التلقائي وأسهم التنقل والنقاط من الإعدادات. أضف الشرائح أو أعد ترتيبها من لوحة الشرائح.",
                'config_schema' => ['height_desktop', 'autoplay_seconds', 'show_dots', 'show_arrows', 'loop', 'transition'],
                'default_config' => ['height_desktop' => '480px', 'autoplay_seconds' => 4, 'show_dots' => true, 'show_arrows' => true, 'loop' => true, 'transition' => 'slide'],
            ],
            [
                'code' => 'countdown_deal', 'group' => 'hero',
                'label_en' => 'Countdown Deal', 'label_ar' => 'صفقة العد التنازلي',
                'icon' => 'clock', 'max_per_page' => 2,
                'description_en' => "A countdown timer banner promoting a time-limited deal. Set the deal title and expiry time — the block automatically counts down and hides itself when the timer reaches zero. Pair with a Product Row or Flash Sale block below it to show the relevant products.",
                'description_ar' => "بانر مؤقت عد تنازلي للترويج لعرض محدود المدة. حدد عنوان الصفقة ووقت انتهائها — تقوم الكتلة بالعد التنازلي تلقائيًا وتختفي عند انتهاء الوقت. اقرنها بكتلة صف منتجات أو تخفيضات سريعة أسفلها لعرض المنتجات ذات الصلة.",
                'config_schema' => ['title_en', 'title_ar', 'ends_at', 'background_color', 'text_color'],
                'default_config' => ['background_color' => '#111827', 'text_color' => '#ffffff'],
            ],
            [
                'code' => 'video_banner', 'group' => 'hero',
                'label_en' => 'Video Banner', 'label_ar' => 'بانر فيديو',
                'icon' => 'play-circle', 'max_per_page' => 3,
                'description_en' => "A full-width video banner. Paste a direct video URL (MP4/WebM) or a CDN link. Set a poster image that shows before the video loads. Autoplay is silent by default — most browsers require videos to be muted to autoplay. Suitable for brand campaigns and seasonal promotions.",
                'description_ar' => "بانر فيديو بعرض الصفحة الكامل. الصق رابط فيديو مباشر (MP4/WebM) أو رابط CDN. حدد صورة غلاف تظهر قبل تحميل الفيديو. التشغيل التلقائي صامت افتراضيًا — تتطلب معظم المتصفحات كتم الصوت للتشغيل التلقائي. مناسب لحملات العلامة التجارية والعروض الموسمية.",
                'config_schema' => ['video_url', 'poster_url', 'autoplay', 'muted'],
                'default_config' => ['autoplay' => true, 'muted' => true],
            ],
            [
                'code' => 'product_row', 'group' => 'products',
                'label_en' => 'Product Row', 'label_ar' => 'صف المنتجات',
                'icon' => 'squares-plus', 'max_per_page' => null,
                'description_en' => "A horizontal row of product cards. Choose a source: Manual (you hand-pick products), Best Sellers, New Arrivals, Featured, Top Rated, or a specific Flash Sale. Filter by category using the Category dropdown. Set how many products show and whether the row scrolls horizontally. The 'View All' link takes customers to that category or collection.",
                'description_ar' => "صف أفقي من بطاقات المنتجات. اختر مصدرًا: يدوي (تختار المنتجات بنفسك)، الأكثر مبيعًا، الأحدث، مميز، الأعلى تقييمًا، أو تخفيضات سريعة محددة. صفِّ حسب الفئة باستخدام القائمة المنسدلة. حدد عدد المنتجات المعروضة وما إذا كان الصف يمكن التمرير فيه أفقيًا. رابط 'عرض الكل' ينقل العملاء إلى تلك الفئة أو المجموعة.",
                'config_schema' => ['title_en', 'title_ar', 'source', 'category_id', 'flash_sale_id', 'items_per_row', 'max_products', 'show_view_all', 'scrollable_row', 'show_ratings', 'show_discount_badge'],
                'default_config' => [
                    'source' => 'best_sellers',
                    'category_id' => null,
                    'flash_sale_id' => null,
                    'items_per_row' => 4,
                    'max_products' => 12,
                    'show_view_all' => true,
                    'scrollable_row' => true,
                    'show_ratings' => true,
                    'show_discount_badge' => true,
                ],
            ],
            [
                'code' => 'flash_sale', 'group' => 'products',
                'label_en' => 'Flash Sale', 'label_ar' => 'تخفيضات سريعة',
                'icon' => 'bolt', 'max_per_page' => 3,
                'description_en' => "A full flash sale display block. Select a Flash Sale from the dropdown — only approved submissions appear as products. Shows a live countdown timer and optional stock-remaining progress bar per item. Background color and badge label are customizable. The block auto-hides when the flash sale ends.",
                'description_ar' => "كتلة عرض كاملة للتخفيضات السريعة. اختر تخفيضًا سريعًا من القائمة المنسدلة — تظهر فقط الطلبات المعتمدة كمنتجات. تعرض مؤقت عد تنازلي مباشر وشريط تقدم اختياري للمخزون المتبقي لكل عنصر. لون الخلفية ونص الشارة قابلان للتخصيص. تختفي الكتلة تلقائيًا عند انتهاء التخفيض السريع.",
                'config_schema' => ['flash_sale_id', 'max_items_shown', 'items_per_row', 'show_countdown', 'show_stock_bar', 'background_color', 'badge_label_en', 'badge_label_ar'],
                'default_config' => ['max_items_shown' => 8, 'items_per_row' => 4, 'show_countdown' => true, 'show_stock_bar' => true, 'background_color' => '#fef2f2'],
            ],
            [
                'code' => 'deal_of_day', 'group' => 'products',
                'label_en' => 'Deal of the Day', 'label_ar' => 'صفقة اليوم',
                'icon' => 'star', 'max_per_page' => 2,
                'description_en' => "A single highlighted product promoted as 'Deal of the Day'. Select any active vendor listing — the block shows its image, title, price, and a countdown to the deal's end time. Typically used once per page, usually below the hero. Disappears automatically when ends_at passes.",
                'description_ar' => "منتج واحد مميز يُعرض كـ'صفقة اليوم'. اختر أي إعلان تاجر نشط — تعرض الكتلة صورته وعنوانه وسعره ومؤقت عد تنازلي لنهاية الصفقة. تُستخدم عادة مرة واحدة في الصفحة، غالبًا أسفل قسم الهيرو. تختفي تلقائيًا عند انتهاء وقت الصفقة.",
                'config_schema' => ['title_en', 'title_ar', 'vendor_listing_id', 'ends_at'],
                'default_config' => [],
            ],
            [
                'code' => 'ad_images_2col', 'group' => 'ads_banners',
                'label_en' => 'Ad Images (2-col)', 'label_ar' => 'صور إعلانية عمودان',
                'icon' => 'view-columns', 'max_per_page' => null,
                'description_en' => "Two promotional banner images displayed side by side (50%/50% split). Each image has its own upload, title overlay, and click destination (URL or internal page/product/category link). Aspect ratio applies to both images simultaneously. Used for paired promotions, e.g. Men's / Women's seasonal campaigns.",
                'description_ar' => "صورتان إعلانيتان تُعرضان جنبًا إلى جنب (تقسيم 50%/50%). لكل صورة رفع خاص بها، وعنوان متراكب، ووجهة عند النقر (رابط أو صفحة/منتج/فئة داخلية). تنطبق نسبة العرض إلى الارتفاع على كلا الصورتين معًا. تُستخدم للعروض المزدوجة، مثل حملات موسمية للرجال / النساء.",
                'config_schema' => ['title_en', 'title_ar', 'aspect_ratio'],
                'default_config' => ['aspect_ratio' => '4:3'],
            ],
            [
                'code' => 'ad_images_4col', 'group' => 'ads_banners',
                'label_en' => 'Ad Images (4-col)', 'label_ar' => 'صور إعلانية أربعة أعمدة',
                'icon' => 'squares-2x2', 'max_per_page' => null,
                'description_en' => "Four promotional images in a 2×2 or 1×4 grid (responsive). Each image links to a destination of your choice. Square aspect ratio (1:1) works best. Suitable for category promotions, brand spotlights, or seasonal collections displayed as visual tiles.",
                'description_ar' => "أربع صور إعلانية في شبكة 2×2 أو 1×4 (متجاوبة). كل صورة ترتبط بوجهة من اختيارك. نسبة العرض إلى الارتفاع المربعة (1:1) هي الأنسب. مناسبة للترويج للفئات، وإبراز العلامات التجارية، أو المجموعات الموسمية المعروضة كبلاطات مرئية.",
                'config_schema' => ['title_en', 'title_ar', 'aspect_ratio'],
                'default_config' => ['aspect_ratio' => '1:1'],
            ],
            [
                'code' => 'full_banner', 'group' => 'ads_banners',
                'label_en' => 'Full Banner', 'label_ar' => 'بانر كامل العرض',
                'icon' => 'photo', 'max_per_page' => null,
                'description_en' => "A single full-width promotional banner spanning the entire page width. Set separate aspect ratios for desktop (default 4:1, wide landscape) and mobile (default 2:1, shorter). Upload one image and set the click destination. Common use: seasonal campaign headers, brand announcements.",
                'description_ar' => "بانر إعلاني واحد بعرض الصفحة الكامل. حدد نسب عرض إلى ارتفاع منفصلة لسطح المكتب (افتراضيًا 4:1، أفقي عريض) والجوال (افتراضيًا 2:1، أقصر). ارفع صورة واحدة وحدد وجهة النقر. الاستخدام الشائع: رؤوس الحملات الموسمية، إعلانات العلامة التجارية.",
                'config_schema' => ['link_url', 'link_type', 'aspect_ratio', 'mobile_aspect_ratio'],
                'default_config' => ['aspect_ratio' => '4:1', 'mobile_aspect_ratio' => '2:1'],
            ],
            [
                'code' => 'category_pills', 'group' => 'discovery',
                'label_en' => 'Category Pills', 'label_ar' => 'أزرار الفئات',
                'icon' => 'tag', 'max_per_page' => null,
                'description_en' => "A row of clickable category pill/tag buttons for quick navigation. Manually select which categories to show, or leave empty to auto-show the top-level categories. Optionally display a product count badge on each pill. Customers tap a pill to browse that category.",
                'description_ar' => "صف من أزرار الفئات القابلة للنقر للتنقل السريع. اختر يدويًا الفئات المراد عرضها، أو اتركها فارغة لعرض الفئات الرئيسية تلقائيًا. يمكن اختياريًا عرض شارة عدد المنتجات على كل زر. ينقر العملاء على الزر لتصفح تلك الفئة.",
                'config_schema' => ['title_en', 'title_ar', 'show_product_count', 'max_items'],
                'default_config' => ['max_items' => 12, 'show_product_count' => true],
            ],
            [
                'code' => 'brand_strip', 'group' => 'discovery',
                'label_en' => 'Brand Strip', 'label_ar' => 'شريط العلامات التجارية',
                'icon' => 'building-storefront', 'max_per_page' => null,
                'description_en' => "A horizontal scrolling strip of brand logos. Add vendors whose store logos you want to feature. When 'Show Logo Only' is enabled, only the image shows (no text below it). Customers can tap a logo to browse that vendor's storefront. Useful for 'Featured Brands' sections.",
                'description_ar' => "شريط أفقي قابل للتمرير من شعارات العلامات التجارية. أضف التجار الذين تريد إبراز شعارات متاجرهم. عند تفعيل 'عرض الشعار فقط'، تظهر الصورة فقط دون نص أسفلها. يمكن للعملاء النقر على شعار لتصفح متجر ذلك التاجر. مفيد لأقسام 'العلامات التجارية المميزة'.",
                'config_schema' => ['title_en', 'title_ar', 'max_items', 'show_logo_only'],
                'default_config' => ['max_items' => 10, 'show_logo_only' => true],
            ],
            [
                'code' => 'search_trends', 'group' => 'discovery',
                'label_en' => 'Search Trends', 'label_ar' => 'توجهات البحث',
                'icon' => 'magnifying-glass', 'max_per_page' => null,
                'description_en' => "Automatically shows the most popular search terms on your site right now, pulled from live search data. No manual content needed — it updates itself. Customers tap a term to search for it. Best placed in the discovery/middle section of the homepage. Shows up to max_terms pills.",
                'description_ar' => "تعرض تلقائيًا أكثر مصطلحات البحث شيوعًا على موقعك حاليًا، مستمدة من بيانات بحث مباشرة. لا حاجة لإدخال محتوى يدويًا — تُحدَّث تلقائيًا. ينقر العملاء على مصطلح للبحث عنه. يُفضَّل وضعها في قسم الاكتشاف/المنتصف من الصفحة الرئيسية. تعرض حتى max_terms من الأزرار.",
                'config_schema' => ['title_en', 'title_ar', 'max_terms'],
                'default_config' => ['max_terms' => 8],
            ],
            [
                'code' => 'countdown_timer', 'group' => 'engagement',
                'label_en' => 'Countdown Timer', 'label_ar' => 'مؤقت العد التنازلي',
                'icon' => 'clock', 'max_per_page' => null,
                'description_en' => "A standalone countdown timer without a product attached. Use it to build anticipation for an upcoming sale, event, or launch. Pair with any other block below it. Hides automatically when the timer reaches zero. Unlike the Countdown Deal block, this does not display a product card — it's a pure timer.",
                'description_ar' => "مؤقت عد تنازلي مستقل دون منتج مرتبط. استخدمه لبناء الترقب لعرض أو حدث أو إطلاق قادم. اقرنه بأي كتلة أخرى أسفله. يختفي تلقائيًا عند وصول المؤقت للصفر. بخلاف كتلة صفقة العد التنازلي، لا تعرض هذه الكتلة بطاقة منتج — إنها مؤقت خالص.",
                'config_schema' => ['title_en', 'title_ar', 'ends_at', 'background_color', 'text_color'],
                'default_config' => ['background_color' => '#111827', 'text_color' => '#ffffff'],
            ],
            [
                'code' => 'text_block', 'group' => 'engagement',
                'label_en' => 'Text Block', 'label_ar' => 'كتلة نصية',
                'icon' => 'document-text', 'max_per_page' => 10,
                'description_en' => "A free-format rich text block. Write HTML content in both English and Arabic — the correct version is shown based on the visitor's language. Controls text alignment and maximum content width. Use for editorial content, policy notices, promotional copy, or any structured text you need on the page. Max 10 text blocks per page.",
                'description_ar' => "كتلة نصية حرة التنسيق. اكتب محتوى HTML بالإنجليزية والعربية — يُعرض الإصدار الصحيح حسب لغة الزائر. تتحكم في محاذاة النص والعرض الأقصى للمحتوى. تُستخدم للمحتوى التحريري، أو الإشعارات السياسية، أو النصوص الترويجية، أو أي نص منظم تحتاجه في الصفحة. حد أقصى 10 كتل نصية لكل صفحة.",
                'config_schema' => ['content_html_en', 'content_html_ar', 'text_align', 'max_width'],
                'default_config' => ['text_align' => 'left', 'max_width' => '1200px'],
            ],
            [
                'code' => 'divider', 'group' => 'engagement',
                'label_en' => 'Divider / Spacer', 'label_ar' => 'فاصل',
                'icon' => 'minus', 'max_per_page' => 20,
                'description_en' => "A horizontal rule or invisible spacer between blocks. Choose solid, dashed, or dotted line style, or 'none' for an invisible spacer. Adjust top/bottom margin in pixels to control vertical spacing between surrounding blocks. Max 20 dividers per page.",
                'description_ar' => "خط أفقي أو فاصل غير مرئي بين الكتل. اختر نمط الخط: متصل، متقطع، منقط، أو 'بلا' لفاصل غير مرئي. اضبط الهامش العلوي/السفلي بالبكسل للتحكم في المسافة الرأسية بين الكتل المحيطة. حد أقصى 20 فاصلًا لكل صفحة.",
                'config_schema' => ['style', 'color', 'margin_top', 'margin_bottom'],
                'default_config' => ['style' => 'solid', 'color' => '#e5e7eb', 'margin_top' => 16, 'margin_bottom' => 16],
            ],
            [
                'code' => 'newsletter_signup', 'group' => 'engagement',
                'label_en' => 'Newsletter Signup', 'label_ar' => 'الاشتراك بالنشرة',
                'icon' => 'envelope', 'max_per_page' => 2,
                'description_en' => "An email newsletter signup form. Customize the headline and subheading in both languages. The subscription form submits to the platform's newsletter endpoint — no additional integration required. Suitable for bottom-of-page placement to capture visitors before they leave. Max 2 per page.",
                'description_ar' => "نموذج اشتراك في النشرة البريدية. خصص العنوان الرئيسي والعنوان الفرعي بكلتا اللغتين. يُرسل نموذج الاشتراك إلى نقطة نهاية النشرة البريدية الخاصة بالمنصة — دون الحاجة لتكامل إضافي. مناسب لوضعه أسفل الصفحة لالتقاط الزوار قبل مغادرتهم. حد أقصى 2 لكل صفحة.",
                'config_schema' => ['title_en', 'title_ar', 'subtitle_en', 'subtitle_ar'],
                'default_config' => [],
            ],
        ];

        $sortOrder = 0;
        foreach ($types as $type) {
            $existing = DB::table('block_types')->where('code', $type['code'])->first();

            $data = [
                'label_en' => $type['label_en'],
                'label_ar' => $type['label_ar'],
                'group' => $type['group'],
                'icon' => $type['icon'],
                'description_en' => $type['description_en'],
                'description_ar' => $type['description_ar'],
                'config_schema' => json_encode($type['config_schema']),
                'default_config' => json_encode((object) $type['default_config']),
                'is_active' => true,
                'requires_permission' => $type['requires_permission'] ?? null,
                'max_per_page' => $type['max_per_page'] ?? null,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('block_types')->where('code', $type['code'])->update($data);
            } else {
                DB::table('block_types')->insert(array_merge($data, [
                    'id' => (string) Str::uuid(),
                    'code' => $type['code'],
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                ]));
            }

            $sortOrder++;
        }
    }
}
