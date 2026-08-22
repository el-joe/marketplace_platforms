<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $author = Admin::first();

        $categories = [
            [
                'name_en'        => 'Platform News',
                'name_ar'        => 'أخبار المنصة',
                'slug'           => 'platform-news',
                'description_en' => 'The latest updates, features, and announcements from our marketplace platform.',
                'description_ar' => 'آخر التحديثات والميزات والإعلانات من منصة السوق لدينا.',
                'color_hex'      => '#3B82F6',
                'icon_name'      => 'megaphone',
                'sort_order'     => 1,
            ],
            [
                'name_en'        => 'Vendor Tips',
                'name_ar'        => 'نصائح البائعين',
                'slug'           => 'vendor-tips',
                'description_en' => 'Practical advice and best practices to help vendors grow their online business.',
                'description_ar' => 'نصائح عملية وأفضل الممارسات لمساعدة البائعين على تنمية أعمالهم عبر الإنترنت.',
                'color_hex'      => '#10B981',
                'icon_name'      => 'light-bulb',
                'sort_order'     => 2,
            ],
            [
                'name_en'        => 'Seller Stories',
                'name_ar'        => 'قصص البائعين',
                'slug'           => 'seller-stories',
                'description_en' => 'Real success stories from vendors who have grown their business on our platform.',
                'description_ar' => 'قصص نجاح حقيقية من بائعين نمّوا أعمالهم على منصتنا.',
                'color_hex'      => '#F59E0B',
                'icon_name'      => 'star',
                'sort_order'     => 3,
            ],
            [
                'name_en'        => 'E-Commerce Insights',
                'name_ar'        => 'رؤى التجارة الإلكترونية',
                'slug'           => 'ecommerce-insights',
                'description_en' => 'Trends, data, and analysis shaping the future of e-commerce in the region.',
                'description_ar' => 'الاتجاهات والبيانات والتحليلات التي تشكّل مستقبل التجارة الإلكترونية في المنطقة.',
                'color_hex'      => '#8B5CF6',
                'icon_name'      => 'chart-bar',
                'sort_order'     => 4,
            ],
        ];

        foreach ($categories as $categoryData) {
            $category = BlogCategory::firstOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );

            $this->seedPostsForCategory($category, $author);
        }
    }

    private function seedPostsForCategory(BlogCategory $category, ?Admin $author): void
    {
        $posts = $this->postsFor($category->slug);

        foreach ($posts as $postData) {
            $wordCount = str_word_count(strip_tags($postData['body_en']));
            $readingTime = (int) ceil($wordCount / 200);

            BlogPost::firstOrCreate(
                ['slug' => $postData['slug']],
                array_merge($postData, [
                    'blog_category_id'    => $category->id,
                    'author_admin_id'     => $author?->id ?? '00000000-0000-0000-0000-000000000001',
                    'published_by_admin_id' => $author?->id ?? '00000000-0000-0000-0000-000000000001',
                    'status'              => 'published',
                    'published_at'        => now()->subDays(rand(1, 30)),
                    'reading_time_minutes' => $readingTime,
                    'is_featured'         => false,
                    'allow_comments'      => false,
                    'views_count'         => rand(50, 500),
                ])
            );
        }
    }

    private function postsFor(string $categorySlug): array
    {
        $map = [
            'platform-news' => [
                [
                    'slug'           => 'introducing-multi-country-storefronts',
                    'title_en'       => 'Introducing Multi-Country Storefronts',
                    'title_ar'       => 'نقدم لكم واجهات المتاجر متعددة البلدان',
                    'excerpt_en'     => 'We\'ve expanded the platform to support storefronts for multiple countries, each with its own locale, currency, and catalog.',
                    'excerpt_ar'     => 'وسّعنا المنصة لدعم واجهات متاجر لدول متعددة، لكلٍّ منها لغتها وعملتها وكتالوجها الخاص.',
                    'body_en'        => '<p>We are thrilled to announce that our marketplace platform now supports fully independent storefronts for multiple countries. This means that vendors can tailor their product listings, pricing, and promotions to each market independently.</p><p>Each country storefront operates under its own domain, allowing customers to browse in their local language and pay in their local currency. This is a major step forward in our commitment to building a truly regional marketplace.</p><p>The rollout begins today across all partner portals. Existing vendors will find a new "Country Settings" section in their dashboard where they can configure country-specific visibility for their listings.</p><p>We will continue to roll out additional country-specific features — including localised shipping rates and tax rules — over the coming weeks. Stay tuned for more updates.</p>',
                    'body_ar'        => '<p>يسعدنا الإعلان عن أن منصة السوق لدينا باتت تدعم الآن واجهات متاجر مستقلة كلياً لدول متعددة. يعني ذلك أن البائعين يمكنهم تخصيص قوائم منتجاتهم وأسعارهم وعروضهم الترويجية لكل سوق بشكل مستقل.</p><p>تعمل كل واجهة متجر لكل دولة تحت نطاقها الخاص، مما يتيح للعملاء التصفح بلغتهم المحلية والدفع بعملتهم المحلية. هذه خطوة مهمة إلى الأمام في التزامنا ببناء سوق إقليمي حقيقي.</p><p>يبدأ الطرح اليوم عبر جميع بوابات الشركاء. سيجد البائعون الحاليون قسمًا جديدًا باسم "إعدادات الدولة" في لوحة التحكم الخاصة بهم.</p>',
                    'tags'           => ['announcement', 'storefronts', 'multi-country'],
                ],
                [
                    'slug'           => 'new-vendor-dashboard-analytics',
                    'title_en'       => 'New Vendor Dashboard Analytics — Know Your Numbers',
                    'title_ar'       => 'تحليلات لوحة تحكم البائع الجديدة — اعرف أرقامك',
                    'excerpt_en'     => 'We\'ve launched a redesigned analytics section in the vendor dashboard with real-time sales charts, conversion funnels, and top-product reports.',
                    'excerpt_ar'     => 'أطلقنا قسم تحليلات مُعاد تصميمه في لوحة تحكم البائع يتضمن مخططات المبيعات الفورية وقمعات التحويل وتقارير أفضل المنتجات.',
                    'body_en'        => '<p>Data-driven selling starts with having the right data. That is why we rebuilt the analytics section of the vendor dashboard from the ground up.</p><p>The new analytics hub gives you a live view of your store performance: daily and weekly revenue charts, conversion rates from product views to purchases, your top-selling products by revenue and units, and a breakdown of traffic sources.</p><p>All charts update in near-real-time so you can react quickly to trending products or spot a sudden drop in conversions before it costs you. Filters let you drill down by date range, product category, or country.</p><p>This feature is available to all vendors on the platform starting today. Log in to your dashboard and navigate to Analytics to explore.</p>',
                    'body_ar'        => '<p>يبدأ البيع المبني على البيانات بامتلاك البيانات الصحيحة. لهذا أعدنا بناء قسم التحليلات في لوحة تحكم البائع من الصفر.</p><p>يمنحك مركز التحليلات الجديد رؤية مباشرة لأداء متجرك: مخططات الإيرادات اليومية والأسبوعية، ومعدلات التحويل من مشاهدات المنتج إلى المشتريات، وأكثر منتجاتك مبيعًا حسب الإيرادات والوحدات، وتحليل مصادر الزيارات.</p><p>تتحدث جميع المخططات في الوقت الفعلي تقريبًا حتى تتمكن من الاستجابة بسرعة للمنتجات الرائجة.</p>',
                    'tags'           => ['analytics', 'dashboard', 'vendors'],
                ],
                [
                    'slug'           => 'platform-scheduled-maintenance-july',
                    'title_en'       => 'Scheduled Maintenance — July 2026',
                    'title_ar'       => 'صيانة مجدولة — يوليو 2026',
                    'excerpt_en'     => 'Our platform will undergo scheduled maintenance on 15 July 2026 from 02:00 to 04:00 GST. Orders placed during this window may be delayed.',
                    'excerpt_ar'     => 'ستخضع المنصة لصيانة مجدولة في 15 يوليو 2026 من الساعة 02:00 حتى 04:00 بتوقيت الخليج. قد تتأخر الطلبات المقدمة خلال هذه الفترة.',
                    'body_en'        => '<p>To ensure continued reliability and performance, we will be performing scheduled infrastructure maintenance on <strong>15 July 2026 from 02:00 to 04:00 GST</strong>.</p><p>During this window, the storefront and vendor dashboard will be in read-only mode. Customers will be able to browse but will not be able to place orders. Vendors will be able to view their dashboard but will not be able to update listings.</p><p>We have chosen this time window because it represents our lowest-traffic period. We expect the maintenance to be completed well within the two-hour window.</p><p>If you have any urgent orders to process, we recommend doing so before 01:30 GST on 15 July. We apologise for any inconvenience and thank you for your patience.</p>',
                    'body_ar'        => '<p>لضمان الموثوقية والأداء المستمر، سنُجري صيانة بنية تحتية مجدولة في <strong>15 يوليو 2026 من الساعة 02:00 حتى 04:00 بتوقيت الخليج</strong>.</p><p>خلال هذه الفترة، ستكون واجهة المتجر ولوحة تحكم البائع في وضع القراءة فقط. يمكن للعملاء التصفح لكن لن يتمكنوا من تقديم طلبات.</p><p>نعتذر عن أي إزعاج ونشكركم على صبركم.</p>',
                    'tags'           => ['maintenance', 'downtime', 'announcement'],
                ],
            ],

            'vendor-tips' => [
                [
                    'slug'           => 'how-to-write-product-titles-that-convert',
                    'title_en'       => 'How to Write Product Titles That Actually Convert',
                    'title_ar'       => 'كيف تكتب عناوين منتجات تحقق تحويلات فعلية',
                    'excerpt_en'     => 'Your product title is the first thing a shopper reads. Here\'s how to write titles that are clear, keyword-rich, and designed to drive clicks.',
                    'excerpt_ar'     => 'عنوان منتجك هو أول ما يقرأه المتسوق. إليك كيفية كتابة عناوين واضحة وغنية بالكلمات المفتاحية ومصممة لزيادة النقرات.',
                    'body_en'        => '<p>On a marketplace with thousands of products, your title is your first — and sometimes only — chance to get a shopper\'s attention. A poorly written title means your listing gets skipped; a well-crafted one can double your click-through rate.</p><h2>Lead with the most important keyword</h2><p>Shoppers search for specific things. Make sure the primary keyword for your product appears within the first 5 words of your title. For example, "Wireless Bluetooth Headphones with Noise Cancelling — 30h Battery" is far stronger than "High Quality Sound Product by BrandX".</p><h2>Include the key spec</h2><p>After the keyword, add the most decision-driving specification: size, colour, material, capacity, or compatibility. This helps the shopper qualify your product before clicking.</p><h2>Keep it under 80 characters</h2><p>Titles longer than 80 characters are often truncated on mobile. Put your most important information first.</p><h2>Avoid keyword stuffing</h2><p>Repeating the same keyword multiple times hurts readability and does not improve search ranking on our platform. Write for the shopper first.</p>',
                    'body_ar'        => '<p>في سوق يضم آلاف المنتجات، يمثّل عنوانك فرصتك الأولى — وأحياناً الوحيدة — لجذب انتباه المتسوق. العنوان المكتوب بشكل سيئ يعني تخطّي قائمتك؛ أما العنوان المصاغ جيداً فيمكنه مضاعفة معدل النقر.</p><h2>ابدأ بالكلمة المفتاحية الأهم</h2><p>يبحث المتسوقون عن أشياء محددة. تأكد من ظهور الكلمة المفتاحية الأساسية لمنتجك ضمن أول 5 كلمات من عنوانك.</p><h2>اذكر المواصفة الأساسية</h2><p>بعد الكلمة المفتاحية، أضف المواصفة الأكثر تأثيراً في قرار الشراء: الحجم، اللون، المادة، السعة، أو التوافق.</p><h2>حافظ على العنوان تحت 80 حرفاً</h2><p>العناوين الأطول من 80 حرفاً غالباً ما تُقطع على الجوال. ضع أهم المعلومات أولاً.</p>',
                    'tags'           => ['listings', 'copywriting', 'conversion'],
                ],
                [
                    'slug'           => 'pricing-strategies-for-marketplace-vendors',
                    'title_en'       => '5 Pricing Strategies Every Marketplace Vendor Should Know',
                    'title_ar'       => '5 استراتيجيات تسعير يجب أن يعرفها كل بائع في السوق',
                    'excerpt_en'     => 'Pricing is not just about being the cheapest. Discover five strategies — from anchor pricing to bundle pricing — that improve margins and win more sales.',
                    'excerpt_ar'     => 'التسعير لا يعني فقط أن تكون الأرخص. اكتشف خمس استراتيجيات — من التسعير المرجعي إلى تسعير الحزم — التي تحسّن الهوامش وتربح المزيد من المبيعات.',
                    'body_en'        => '<p>Price is the single most-scrutinised element of any product listing. Yet many vendors set prices reactively — either matching the lowest competitor or guessing what feels right. Here are five deliberate strategies.</p><h2>1. Anchor Pricing</h2><p>Show the original price alongside the sale price. The "was/now" contrast makes the sale price feel like a great deal even if it has always been that price.</p><h2>2. Bundle Pricing</h2><p>Group related products together at a discount. Bundles increase average order value and make it harder for competitors to price-match directly.</p><h2>3. Charm Pricing</h2><p>Prices ending in 9 or 99 (e.g., 49.99 instead of 50.00) are processed by shoppers as significantly lower, even though the difference is negligible.</p><h2>4. Value-Based Pricing</h2><p>Price based on what your product is worth to the customer, not just your cost plus margin. Premium materials, unique design, or a strong brand can justify a price above the market average.</p><h2>5. Loss Leader Pricing</h2><p>Price one high-traffic product at or near cost to drive shoppers to your store, then rely on related-product purchases for margin.</p>',
                    'body_ar'        => '<p>السعر هو العنصر الأكثر فحصاً في أي قائمة منتج. ومع ذلك، يحدد كثير من البائعين الأسعار بشكل تفاعلي. إليك خمس استراتيجيات متعمدة.</p><h2>1. التسعير المرجعي</h2><p>اعرض السعر الأصلي إلى جانب سعر البيع. يجعل التباين "كان/الآن" سعر البيع يبدو صفقة رائعة.</p><h2>2. تسعير الحزم</h2><p>اجمع المنتجات ذات الصلة بخصم. تزيد الحزم من متوسط قيمة الطلب.</p><h2>3. التسعير بالأرقام السحرية</h2><p>الأسعار المنتهية بـ 9 أو 99 يعالجها المتسوقون كأسعار أقل بكثير.</p><h2>4. التسعير القائم على القيمة</h2><p>سعّر بناءً على ما يستحقه منتجك للعميل وليس فقط تكلفتك مضافاً إليها هامش الربح.</p><h2>5. التسعير القائد بالخسارة</h2><p>سعّر منتجاً ذا حركة مرور عالية بالتكلفة أو قريبة منها لجذب المتسوقين إلى متجرك.</p>',
                    'tags'           => ['pricing', 'strategy', 'sales'],
                ],
                [
                    'slug'           => 'optimising-product-images-for-conversions',
                    'title_en'       => 'Optimising Product Images for Higher Conversions',
                    'title_ar'       => 'تحسين صور المنتج لتحقيق تحويلات أعلى',
                    'excerpt_en'     => 'Blurry or poorly lit product images are costing you sales. Learn the six image rules top-performing vendors follow on our platform.',
                    'excerpt_ar'     => 'الصور الضبابية أو السيئة الإضاءة تُكلّفك مبيعات. تعرّف على القواعد الست للصور التي يتبعها أفضل البائعين أداءً على منصتنا.',
                    'body_en'        => '<p>Online shoppers cannot touch, smell, or try on your product. Your images are their only physical reference. This is why product photography has a direct impact on conversion rate.</p><h2>Rule 1: Use a white or neutral background for the hero shot</h2><p>A clean background removes distractions and makes the product the star. It also ensures your listing looks consistent alongside other listings on search results pages.</p><h2>Rule 2: Shoot at least 5 angles</h2><p>Front, back, side, close-up detail, and in-use or lifestyle shot. Shoppers who see more angles buy with more confidence and return less often.</p><h2>Rule 3: Minimum 1000×1000 pixels</h2><p>Our platform supports zoom on hover. Images below 1000px look blurry when zoomed. Use at least 1500×1500 for best results.</p><h2>Rule 4: Show scale</h2><p>If size matters for your product — and it usually does — include an image with a size reference (a hand, a common object, a ruler).</p><h2>Rule 5: Avoid text overlays on the hero image</h2><p>Text on product images looks like promotional banners and erodes trust. Put specs in the title and bullet points instead.</p><h2>Rule 6: Use consistent lighting</h2><p>Inconsistent lighting across your image set makes your store look unprofessional. Natural diffused light or a lightbox gives you the most reliable results.</p>',
                    'body_ar'        => '<p>لا يستطيع المتسوقون عبر الإنترنت لمس منتجك أو شمّه أو تجربته. صورك هي مرجعهم المادي الوحيد.</p><h2>القاعدة 1: استخدم خلفية بيضاء أو محايدة للصورة الرئيسية</h2><p>الخلفية النظيفة تزيل المشتتات وتجعل المنتج هو النجم.</p><h2>القاعدة 2: التقط صوراً من 5 زوايا على الأقل</h2><p>أمام، خلف، جانب، تفصيل مقرّب، وصورة استخدام أو نمط حياة.</p><h2>القاعدة 3: الحد الأدنى 1000×1000 بكسل</h2><p>منصتنا تدعم التكبير عند التحويم. الصور أقل من 1000 بكسل تبدو ضبابية عند التكبير.</p><h2>القاعدة 4: أظهر الحجم</h2><p>إذا كان الحجم مهماً لمنتجك، أدرج صورة مع مرجع للحجم.</p><h2>القاعدة 5: تجنب تراكبات النص على الصورة الرئيسية</h2><p>النص على صور المنتج يبدو كلافتات ترويجية ويضعف الثقة.</p><h2>القاعدة 6: استخدم إضاءة متسقة</h2><p>الإضاءة غير المتسقة عبر مجموعة صورك تجعل متجرك يبدو غير احترافي.</p>',
                    'tags'           => ['photography', 'listings', 'conversion'],
                ],
            ],

            'seller-stories' => [
                [
                    'slug'           => 'how-amira-grew-her-abaya-brand-300-percent',
                    'title_en'       => 'How Amira Grew Her Abaya Brand 300% in One Year',
                    'title_ar'       => 'كيف نمّت أميرة علامتها التجارية للعبايات بنسبة 300% في عام واحد',
                    'excerpt_en'     => 'Amira Al-Rashidi started selling modest fashion from her living room. A year later she is one of the platform\'s top-10 fashion vendors. Here is her story.',
                    'excerpt_ar'     => 'بدأت أميرة الراشدي بيع الأزياء المحتشمة من غرفة معيشتها. بعد عام أصبحت من أفضل 10 بائعين في قطاع الأزياء على المنصة. هذه قصتها.',
                    'body_en'        => '<p>Amira Al-Rashidi had been designing abayas for friends and family for years before she decided to take her passion online. "I was terrified," she admits. "I had no e-commerce experience, no big budget for photography, just a phone camera and a lot of hope."</p><p>She joined the platform in June 2024 with five designs and a determination to treat every customer review as a free consultant. Within three months she had earned her first 50 five-star reviews and was reinvesting every dirham of profit into better fabric and a proper photo setup.</p><p>The turning point came when she ran her first flash sale. "I cut 20% off my best-seller for 48 hours and the notifications didn\'t stop," she says. "I sold in two days what used to take two weeks."</p><p>Today, Amira employs three seamstresses, ships to six countries, and is preparing to launch a kids\' line. Her advice to new vendors: "Reply to every message within an hour. Speed builds trust faster than any marketing budget."</p>',
                    'body_ar'        => '<p>كانت أميرة الراشدي تصمم العبايات لأصدقائها وعائلتها لسنوات قبل أن تقرر أخذ شغفها إلى الإنترنت. "كنت خائفة جداً،" تعترف. "لم يكن لديّ خبرة في التجارة الإلكترونية، ولا ميزانية كبيرة للتصوير، فقط كاميرا هاتف وكثير من الأمل."</p><p>انضمت إلى المنصة في يونيو 2024 بخمسة تصميمات وتصميم على معاملة كل تقييم من العملاء كمستشار مجاني. في غضون ثلاثة أشهر حصلت على أول 50 تقييم خمس نجوم.</p><p>جاءت نقطة التحول عندما أجرت أول تخفيض سريع لها. "خفّضت 20% على أكثر منتجاتي مبيعاً لمدة 48 ساعة ولم تتوقف الإشعارات،" تقول. "بعت في يومين ما كان يستغرق أسبوعين."</p><p>اليوم توظّف أميرة ثلاث خياطات وتشحن إلى ست دول. نصيحتها للبائعين الجدد: "ردّ على كل رسالة خلال ساعة. السرعة تبني الثقة أسرع من أي ميزانية تسويقية."</p>',
                    'tags'           => ['success-story', 'fashion', 'growth'],
                ],
                [
                    'slug'           => 'from-garage-to-fulfillment-center-karim-story',
                    'title_en'       => 'From Garage to Fulfilment Centre: Karim\'s Electronics Journey',
                    'title_ar'       => 'من الجراج إلى مركز التوصيل: رحلة كريم في الإلكترونيات',
                    'excerpt_en'     => 'Karim started selling refurbished phones from his garage. Three years on, he runs a dedicated fulfilment warehouse and ships 200+ orders a day.',
                    'excerpt_ar'     => 'بدأ كريم بيع الهواتف المُجدَّدة من جراجه. بعد ثلاث سنوات يدير مستودع توصيل مخصصاً ويشحن أكثر من 200 طلب يومياً.',
                    'body_en'        => '<p>In 2022, Karim Hassan was a telecom technician with a side hustle: buying damaged smartphones, repairing them, and reselling on local classifieds. The margins were good but the audience was tiny. A colleague suggested trying an online marketplace.</p><p>"My first listing got two views in the first week," he laughs. "I nearly gave up." He didn\'t. Instead he studied how the platform search algorithm worked, optimised his titles, and shot proper product photos against a white sheet pinned to his garage wall.</p><p>By month three, he was shipping 15 orders a day. By month twelve, 80. Today, Karim\'s warehouse processes over 200 orders daily, carries a 4.9-star rating across 8,000+ reviews, and operates a small repair team that turns around refurbished units in 24 hours.</p><p>"The platform gave me the reach," he says. "But it was the reviews that gave me the credibility. I obsessed over every single one."</p>',
                    'body_ar'        => '<p>في عام 2022، كان كريم حسان فنياً في مجال الاتصالات مع عمل جانبي: شراء الهواتف الذكية التالفة وإصلاحها وإعادة بيعها في الإعلانات المحلية.</p><p>"حصل إعلاني الأول على مشاهدتين في الأسبوع الأول،" يضحك. "كدت أستسلم." لكنه لم يفعل. بدلاً من ذلك درس كيف يعمل خوارزمية بحث المنصة وحسّن عناوينه والتقط صوراً احترافية.</p><p>بحلول الشهر الثالث كان يشحن 15 طلباً يومياً. اليوم يعالج مستودع كريم أكثر من 200 طلب يومياً ويحمل تقييم 4.9 نجوم عبر أكثر من 8000 تقييم.</p><p>"المنصة أعطتني الوصول،" يقول. "لكن التقييمات هي التي أعطتني المصداقية."</p>',
                    'tags'           => ['success-story', 'electronics', 'growth'],
                ],
                [
                    'slug'           => 'building-a-food-brand-from-a-home-kitchen',
                    'title_en'       => 'Building a Food Brand from a Home Kitchen',
                    'title_ar'       => 'بناء علامة تجارية غذائية من مطبخ منزلي',
                    'excerpt_en'     => 'Nour turned her grandmother\'s za\'atar recipe into a thriving packaged food business without ever leaving her kitchen. Here\'s how she did it.',
                    'excerpt_ar'     => 'حوّلت نور وصفة جدتها للزعتر إلى أعمال تجارية مزدهرة في مجال الأغذية المعبأة دون أن تغادر مطبخها. إليك كيف فعلت ذلك.',
                    'body_en'        => '<p>Nour Khalil had a secret weapon: her grandmother\'s za\'atar blend, a recipe passed down three generations and never shared outside the family. When friends kept asking where to buy it, Nour saw an opportunity.</p><p>She spent three months getting her home kitchen certified for food production, designed a simple label on her laptop, and listed her first product — a 200g jar of hand-mixed za\'atar — on the platform in early 2025.</p><p>The first order came within hours. "I cried," she says. "Someone I didn\'t know was willing to pay for something I made."</p><p>Word spread quickly. Customers started tagging her on social media. She added two more SKUs: a chilli-laced version and a za\'atar-olive oil dipping blend. By the end of 2025, she was selling 1,200 jars a month and had moved to a small licensed production space.</p><p>Her biggest lesson: "Packaging matters as much as the product. I invested in proper jars and a professionally printed label early on, and I think that\'s what made people trust a brand they\'d never heard of."</p>',
                    'body_ar'        => '<p>كان لدى نور خليل سلاح سري: مزيج الزعتر الخاص بجدتها، وصفة توارثتها ثلاثة أجيال ولم تُشارَك قط خارج العائلة.</p><p>أمضت ثلاثة أشهر في الحصول على شهادة مطبخها المنزلي لإنتاج الغذاء، وصمّمت علامة بسيطة على حاسوبها، وأدرجت منتجها الأول على المنصة في مطلع 2025.</p><p>جاء الطلب الأول في غضون ساعات. "بكيت،" تقول. "كان هناك شخص لا أعرفه على استعداد للدفع مقابل شيء صنعته."</p><p>بحلول نهاية 2025 كانت تبيع 1200 جرة شهرياً. درسها الأكبر: "التغليف بنفس أهمية المنتج. استثمرت في جرات مناسبة وعلامة مطبوعة احترافياً مبكراً، وأعتقد أن ذلك هو ما جعل الناس يثقون بعلامة تجارية لم يسمعوا بها قط."</p>',
                    'tags'           => ['success-story', 'food', 'home-business'],
                ],
            ],

            'ecommerce-insights' => [
                [
                    'slug'           => 'mobile-commerce-trends-gulf-2026',
                    'title_en'       => 'Mobile Commerce Trends in the Gulf — What the Data Says for 2026',
                    'title_ar'       => 'اتجاهات التجارة عبر الجوال في الخليج — ما تقوله البيانات لعام 2026',
                    'excerpt_en'     => 'Over 78% of orders on our platform are now placed from mobile devices. We break down the numbers and what they mean for how you sell.',
                    'excerpt_ar'     => 'أكثر من 78% من الطلبات على منصتنا تُقدَّم الآن من الأجهزة المحمولة. نحلّل الأرقام وما تعنيه لطريقة بيعك.',
                    'body_en'        => '<p>Mobile commerce is not the future of retail in the Gulf — it is the present. Platform data for H1 2026 shows that 78.4% of all orders were placed on a smartphone or tablet, up from 71% in the same period last year.</p><h2>Peak shopping hours are late evening</h2><p>The busiest shopping period is 21:00–23:00 local time across all countries on the platform. Vendors who schedule flash sales or push notifications in this window consistently see 30–40% higher engagement than those who broadcast during business hours.</p><h2>Session length is shortening</h2><p>Average mobile session length dropped from 6.2 minutes to 4.8 minutes year-on-year. Shoppers are making faster decisions. This means your first product image and title need to do more work in less time — there is no room for ambiguity.</p><h2>Cart abandonment is highest on Friday afternoon</h2><p>Friday afternoon sees the highest cart abandonment rate (67%) of any time slot. Automated cart-recovery messages sent within 2 hours of abandonment recover 18% of those carts on average.</p><h2>What this means for your store</h2><p>Optimise your listing images for small screens. Keep titles concise. Set up cart-recovery automations. And consider timing your best promotions for the 21:00–23:00 window.</p>',
                    'body_ar'        => '<p>التجارة عبر الجوال ليست مستقبل التجزئة في الخليج — إنها الحاضر. تُظهر بيانات المنصة للنصف الأول من 2026 أن 78.4% من جميع الطلبات قُدِّمت من هاتف ذكي أو جهاز لوحي.</p><h2>ذروة ساعات التسوق في المساء المتأخر</h2><p>أكثر فترات التسوق ازدحاماً هي 21:00-23:00 بالتوقيت المحلي. البائعون الذين يجدولون التخفيضات السريعة في هذه الفترة يرون باستمرار تفاعلاً أعلى بنسبة 30-40%.</p><h2>مدة الجلسة تتقلص</h2><p>انخفض متوسط مدة جلسة الجوال من 6.2 دقيقة إلى 4.8 دقيقة على أساس سنوي. المتسوقون يتخذون قرارات أسرع.</p><h2>التخلي عن سلة التسوق أعلى مساء الجمعة</h2><p>يشهد مساء الجمعة أعلى معدل تخلي عن سلة التسوق (67%). رسائل استرداد السلة المُرسلة خلال ساعتين تسترد 18% من تلك السلال في المتوسط.</p>',
                    'tags'           => ['mobile', 'data', 'trends', 'gulf'],
                ],
                [
                    'slug'           => 'ramadan-commerce-playbook-2026',
                    'title_en'       => 'The Ramadan Commerce Playbook for 2026',
                    'title_ar'       => 'دليل التجارة في رمضان لعام 2026',
                    'excerpt_en'     => 'Ramadan is the biggest e-commerce event of the year in the region. Here\'s how to prepare your store, plan your promotions, and protect your margins.',
                    'excerpt_ar'     => 'رمضان هو أكبر حدث للتجارة الإلكترونية في المنطقة خلال العام. إليك كيفية تجهيز متجرك وتخطيط عروضك وحماية هوامشك.',
                    'body_en'        => '<p>Ramadan consistently drives the highest order volumes of the year on our platform — in 2025, Ramadan month accounted for 22% of the full year\'s GMV. For 2026, the window runs from approximately 1 March to 30 March. Here is how to make the most of it.</p><h2>Stock up 4 weeks early</h2><p>Supply chains tighten in the weeks leading up to Ramadan. Place your restocking orders by early February to avoid stockouts during peak demand.</p><h2>Adjust your listing copy</h2><p>Shoppers in Ramadan are often buying gifts, food, and home items for entertaining. Reframe your product descriptions around the occasion where relevant: "Perfect Ramadan gift", "Ideal for Iftar gatherings".</p><h2>Plan your promotions calendar</h2><p>The most effective Ramadan promotions run during: the first week (novelty excitement), mid-Ramadan (the "lull" that benefits from a boost), and the last 10 days (intense buying for Eid).</p><h2>Protect your margins</h2><p>Every vendor discounts during Ramadan. Instead of matching every competitor, pick two or three products for deep discounts and keep the rest at your normal price with a compelling Ramadan narrative.</p><h2>Prepare your customer service</h2><p>Order volume spikes mean enquiry volume spikes. Pre-write responses to your 10 most common questions and make sure someone is monitoring messages during evening Iftar hours.</p>',
                    'body_ar'        => '<p>يُحقق رمضان باستمرار أعلى أحجام طلبات في العام على منصتنا — في 2025 شكّل شهر رمضان 22% من إجمالي حجم المعاملات السنوي.</p><h2>احتياطياتك قبل 4 أسابيع</h2><p>تتشدد سلاسل التوريد في الأسابيع التي تسبق رمضان. ضع طلبات إعادة التخزين بحلول مطلع فبراير لتجنب نفاد المخزون خلال ذروة الطلب.</p><h2>اضبط نص قوائمك</h2><p>غالباً ما يشتري المتسوقون في رمضان هدايا ومواد غذائية وأغراض منزلية. أعد صياغة أوصاف منتجاتك حول المناسبة: "هدية رمضان المثالية"، "مثالي لتجمعات الإفطار".</p><h2>خطط تقويم عروضك</h2><p>أكثر العروض الترويجية فعالية تعمل خلال: الأسبوع الأول، ومنتصف رمضان، والعشر الأواخر (شراء مكثف لعيد الفطر).</p><h2>احمِ هوامشك</h2><p>كل بائع يخفّض في رمضان. بدلاً من مجاراة كل منافس، اختر منتجين أو ثلاثة لخصومات كبيرة وأبقِ الباقي بسعرك العادي.</p>',
                    'tags'           => ['ramadan', 'seasonal', 'promotions', 'strategy'],
                ],
                [
                    'slug'           => 'the-rise-of-social-commerce-in-the-region',
                    'title_en'       => 'The Rise of Social Commerce in the Region',
                    'title_ar'       => 'صعود التجارة الاجتماعية في المنطقة',
                    'excerpt_en'     => 'Social media is no longer just a marketing channel — it is becoming a direct sales channel. Here\'s what the shift means for marketplace vendors.',
                    'excerpt_ar'     => 'لم تعد وسائل التواصل الاجتماعي مجرد قناة تسويق — إنها تتحول إلى قناة مبيعات مباشرة. إليك ما يعنيه هذا التحول لبائعي السوق.',
                    'body_en'        => '<p>Social commerce — the practice of buying directly through social media platforms — is growing faster in the MENA region than anywhere else in the world. A 2025 report by a leading regional research firm found that 41% of Gulf internet users had completed a purchase through a social platform in the previous 12 months, up from 28% in 2023.</p><h2>What is driving the growth?</h2><p>Three factors are converging: the Gulf\'s extremely high social media penetration (over 90% in the UAE and KSA), the cultural preference for recommendation-based discovery, and the maturation of in-app payment infrastructure.</p><h2>How does this affect marketplace vendors?</h2><p>Social commerce and marketplace commerce are not mutually exclusive — in fact, the most successful vendors use social channels to drive discovery and their marketplace store to handle fulfilment, reviews, and trust.</p><p>The practical implication: invest in short-form video content that showcases your products, link directly to your marketplace listings, and monitor which social platforms drive your highest-converting traffic using your dashboard analytics.</p><h2>The outlook for 2026–2027</h2><p>We expect in-app shopping features to expand further across the region\'s major social platforms. Vendors who build a social audience now will have a significant advantage when those features mature.</p>',
                    'body_ar'        => '<p>التجارة الاجتماعية — ممارسة الشراء مباشرة عبر منصات التواصل الاجتماعي — تنمو في منطقة الشرق الأوسط وشمال أفريقيا بشكل أسرع من أي مكان آخر في العالم.</p><h2>ما الذي يقود النمو؟</h2><p>ثلاثة عوامل تتقاطع: انتشار وسائل التواصل الاجتماعي المرتفع جداً في الخليج، والتفضيل الثقافي للاكتشاف القائم على التوصيات، ونضج البنية التحتية للدفع داخل التطبيقات.</p><h2>كيف يؤثر هذا على بائعي السوق؟</h2><p>التجارة الاجتماعية وتجارة السوق ليستا متعارضتين — في الواقع، يستخدم أنجح البائعين القنوات الاجتماعية للاكتشاف ومتجرهم في السوق للتعامل مع التوصيل والتقييمات والثقة.</p><h2>التوقعات لـ 2026-2027</h2><p>نتوقع أن تتوسع ميزات التسوق داخل التطبيقات عبر منصات التواصل الاجتماعي الكبرى في المنطقة. البائعون الذين يبنون جمهوراً اجتماعياً الآن سيتمتعون بميزة كبيرة عندما تنضج تلك الميزات.</p>',
                    'tags'           => ['social-commerce', 'trends', 'strategy'],
                ],
            ],
        ];

        return $map[$categorySlug] ?? [];
    }
}
