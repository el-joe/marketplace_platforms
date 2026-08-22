<?php

namespace Database\Seeders;

use App\Models\PortalContent;
use Illuminate\Database\Seeder;

/**
 * Batch 2 of the "Portal Content" CMS seeder — covers the advertise-* pages
 * and partials (brands, advertisers, display, product, request) converted
 * to use portal_content()/portal_link()/portal_image() in
 * resources/views/portal/advertise-*.blade.php and
 * resources/views/portal/partials/{advertise-footer,advertise-request-form,
 * advertisers-*,brands-*,display-*,product-*}.blade.php.
 *
 * Follows the identical mechanical pattern documented in
 * PortalContentSeeder.php. The advertise-nav.blade.php labels are already
 * seeded under page_key='nav', block_key='advertise_nav' by
 * PortalContentSeeder.php and are intentionally NOT duplicated here.
 */
class PortalContentSeederBatch2 extends Seeder
{
    public function run(): void
    {
        $rows = [];

        // ─── page_key = 'advertise-brands' ─────────────────────────────────
        $rows[] = ['advertise-brands', 'meta', 'title', 'text', 'Popular Ad Solutions | Brands - noon', 'حلول الإعلانات ذات الشعبية | العلامات التجارية - نون', null, 1];
        $rows[] = ['advertise-brands', 'meta', 'description', 'text', 'Boost your brand awareness, reach large audiences, and connect with customers by leveraging noon ads strategic products.', 'قم بتعزيز الوعي بعلامتك التجارية، والوصول إلى عملاء أكثر، والتواصل مع العملاء من خلال الاستفادة من المنتجات الإستراتيجية لإعلانات نون', null, 2];

        $rows[] = ['advertise-brands', 'hero', 'image', 'image', 'Brands', 'العلامات التجارية', 'https://advertise.noon.com/images/brands-home.png', 10];
        $rows[] = ['advertise-brands', 'hero', 'cta_button', 'link', 'Contact us', 'اتصل بنا', null, 11];
        $rows[] = ['advertise-brands', 'hero', 'eyebrow', 'text', 'Brands', 'العلامات التجارية', null, 12];
        $rows[] = ['advertise-brands', 'hero', 'subtitle', 'text', 'Boost your brand awareness, reach large audiences, and connect with customers by leveraging noon ads strategic products.', 'قم بتعزيز الوعي بعلامتك التجارية، والوصول إلى عملاء أكثر، والتواصل مع العملاء من خلال الاستفادة من المنتجات الإستراتيجية لإعلانات نون', null, 13];
        $rows[] = ['advertise-brands', 'hero', 'subtitle_2', 'text', 'Video Ads are now available on noon ads! Take your advertising to the next level by enhancing user browsing experience with a 6 to 45 second video.', 'الإعلانات الفيديوية متاحة الآن على إعلانات العلامة التجارية! ارتق بإعلاناتك إلى المستوى التالي من خلال تحسين تصفح المستخدمين بفيديو يتراوح مدته بين 6 إلى 45 ثانية.', null, 14];
        $rows[] = ['advertise-brands', 'hero', 'solutions_title', 'text', 'Popular Ad Solutions', 'حلول الإعلانات ذات الشعبية', null, 15];

        $rows[] = ['advertise-brands', 'solutions_item_1', 'title', 'text', 'Display Ads', 'إعلانات العرض', null, 20];
        $rows[] = ['advertise-brands', 'solutions_item_1', 'description', 'text', 'Highlight specific campaigns you want to push, including new launches, clearance items, or seasonal offerings to target audiences likely to be interested.', 'قم بتسليط الضوء على الحملات المحددة التي ترغب في الترويج لها، بما في ذلك عمليات الإطلاق الجديدة أو منتجات التصفية أو العروض الموسمية لاستهداف الجماهير التي من المحتمل أن تكون مهتمة.', null, 21];
        $rows[] = ['advertise-brands', 'solutions_item_1', 'link', 'link', 'Learn More', 'اعرف أكثر', null, 22];
        $rows[] = ['advertise-brands', 'solutions_item_1', 'image', 'image', 'Display Ads', 'إعلانات العرض', 'https://advertise.noon.com/images/productPhone_ar.png', 23];

        $rows[] = ['advertise-brands', 'solutions_item_2', 'title', 'text', 'Brand Ads', 'إعلانات العلامة التجارية', null, 24];
        $rows[] = ['advertise-brands', 'solutions_item_2', 'description', 'text', "Promote your products and brand in a visually appealing and prominent way within noon's browse, search, and relevant product detail pages. Display multiple products within one ad, showcasing a wider range of offerings and potentially attracting a broader audience.", 'قم بالترويج لمنتجاتك وعلامتك التجارية بطريقة بصرية جذابة وبارزة داخل صفحات نون للتصفح، البحث، وصفحات تفاصيل المنتج ذات الصلة. بمكنك أيضًا عرض منتجات متعددة داخل إعلان واحد، لإبراز مجموعة واسعة من منتجاتك وجذب جمهور أكبر.', null, 25];
        $rows[] = ['advertise-brands', 'solutions_item_2', 'link', 'link', 'Learn More', 'اعرف أكثر', null, 26];
        $rows[] = ['advertise-brands', 'solutions_item_2', 'image', 'image', 'Brand Ads', 'إعلانات العلامة التجارية', 'https://advertise.noon.com/images/brandproduct_ar.png', 27];

        $rows[] = ['advertise-brands', 'solutions_item_3', 'title', 'text', 'Product Ads', 'إعلانات المنتجات', null, 28];
        $rows[] = ['advertise-brands', 'solutions_item_3', 'description', 'text', 'Amplify your products visibility on the lower funnel with targeted ads that reach a larger customer base and enable growth. This feature will significantly increase product visibility, customer reach and conversion potential.', 'قم بتعزيز رؤية منتجاتك في الأسفل باستخدام الإعلانات المستهدفة التي تصل إلى قاعدة عملاء أكبر وتمكن النمو. سيعمل هذا المنتج على زيادة ظهور منتجاتك بشكل كبير، والوصول إلى العملاء، وإمكانات التحويل.', null, 29];
        $rows[] = ['advertise-brands', 'solutions_item_3', 'link', 'link', 'Learn More', 'اعرف أكثر', null, 30];
        $rows[] = ['advertise-brands', 'solutions_item_3', 'image', 'image', 'Product Ads', 'إعلانات المنتجات', 'https://advertise.noon.com/images/productAds_ar.png', 31];

        $rows[] = ['advertise-brands', 'solutions_item_4', 'title', 'text', 'Managed Display Ads', 'إدارة العرض', null, 32];
        $rows[] = ['advertise-brands', 'solutions_item_4', 'description', 'text', 'Obtain additional support from noon ads specialists to access premium onsite placements and reach wider audiences with the support of our specialist.', 'احصل على دعم إضافي من متخصصي إعلانات نون للوصول إلى مواضع متميزة في الموقع والوصول إلى جماهير أوسع بدعم من متخصصينا', null, 33];
        $rows[] = ['advertise-brands', 'solutions_item_4', 'link', 'link', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', null, 34];
        $rows[] = ['advertise-brands', 'solutions_item_4', 'image', 'image', 'Managed Display Ads', 'إدارة العرض', 'https://advertise.noon.com/images/managedDisplayAds_ar.png', 35];

        $rows[] = ['advertise-brands', 'solutions_item_5', 'title', 'text', 'CRM and Social Media', 'إدارة العلاقات مع العملاء ووسائل التواصل الاجتماعي', null, 36];
        $rows[] = ['advertise-brands', 'solutions_item_5', 'description', 'text', 'Engage your audience with targeted push notifications and social media campaigns leveraging our wide network of marketers and influencers.', 'تفاعل مع جمهورك من خلال إشعارات الدفع المستهدفة وحملات وسائل التواصل الاجتماعي باستخدام شبكتنا الواسعة من المسوقين والمؤثرين.', null, 37];
        $rows[] = ['advertise-brands', 'solutions_item_5', 'link', 'link', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', null, 38];
        $rows[] = ['advertise-brands', 'solutions_item_5', 'image', 'image', 'CRM and Social Media', 'إدارة العلاقات مع العملاء ووسائل التواصل الاجتماعي', 'https://advertise.noon.com/images/crm.png', 39];

        $rows[] = ['advertise-brands', 'solutions_item_6', 'title', 'text', 'Offsite Solutions', 'حلول خارج الموقع', null, 40];
        $rows[] = ['advertise-brands', 'solutions_item_6', 'description', 'text', 'Attract millions of eyes with high impact OOH solutions strategically placed in high-traffic locations tailored to your ideal audience or go straight to their doorsteps with personalized and targeted BTL marketing.', 'قم بجذب الملايين من المتسوقين من خلال حلول الإعلان الخارجي ذات التأثير العالي والتي تم وضعها بشكل استراتيجي في مواقع ذات حركة مرور كبيرة، وهي مصممة خصيصًا لجمهورك المستهدف. أو يمكنك أن تذهب مباشرة إليهم عبر تسويق تحت الخط (BTL) الشخصي والمستهدف.', null, 41];
        $rows[] = ['advertise-brands', 'solutions_item_6', 'link', 'link', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', null, 42];
        $rows[] = ['advertise-brands', 'solutions_item_6', 'image', 'image', 'Offsite Solutions', 'حلول خارج الموقع', 'https://advertise.noon.com/images/holdingImage2.png', 43];

        $rows[] = ['advertise-brands', 'testimonials', 'eyebrow', 'text', 'Hear from our satisfied customers', 'استمع إلى آراء عملائنا', null, 50];
        $rows[] = ['advertise-brands', 'testimonials', 'prev_label', 'text', 'Previous', 'السابق', null, 51];
        $rows[] = ['advertise-brands', 'testimonials', 'next_label', 'text', 'Next', 'التالي', null, 52];
        $rows[] = ['advertise-brands', 'testimonials', 'slide_label', 'text', 'Slide', 'الشريحة', null, 53];

        $rows[] = ['advertise-brands', 'testimonial_1', 'quote', 'text', "'Our partnership with noon during Yellow Friday has been a remarkable success, showcasing our growth and innovation. We are proud to exclusively offer our 2nd generation Freestyle Projector through noon, and we anticipate outstanding results. Together, we are leading the way in delivering cutting-edge technology to our valued customers.'", "'لقد حققت شراكتنا مع نون خلال يوم الجمعة الصفراء نجاحًا ملحوظًا، حيث أظهرت نمونا وابتكارنا. نحن فخورون بأن نقدم حصريًا الجيل الثاني من جهاز العرض فري ستايل في نون، ونتوقع نتائج رائعة. معًا، نحن نقود الطريق في تقديم التكنولوجيا المتطورة لعملائنا الكرام.'", null, 54];
        $rows[] = ['advertise-brands', 'testimonial_1', 'name', 'text', 'Ahmed Sultan', 'احمد سلطان', null, 55];
        $rows[] = ['advertise-brands', 'testimonial_1', 'position', 'text', 'Product Marketing Manager, Samsung', 'مدير تسويق المنتجات سامسونج', null, 56];

        $rows[] = ['advertise-brands', 'testimonial_2', 'quote', 'text', "'Since integrating noon's two new data dashboards in 2022 and 2023, our data sharing capabilities have soared, leading to enhanced ROI across all account media spends. noon's innovative solutions have truly optimized our partnership and elevated our strategic decisions to unprecedented levels of success'", "'منذ دمج لوحتي بيانات نون الجديدتين في عامي 2022 و 2023، تجاوزت قدراتنا في مشاركة البيانات، مما أدى إلى تعزيز عائد الاستثمار في جميع النفقات الإعلامية للحساب. لقد قامت حلول نون المبتكرة حقًا بتحسين شراكتنا ورفع قراراتنا الاستراتيجية إلى مستويات نجاح غير مسبوقة.'", null, 57];
        $rows[] = ['advertise-brands', 'testimonial_2', 'name', 'text', 'Dania Elhussein', 'دانيا الحسين', null, 58];
        $rows[] = ['advertise-brands', 'testimonial_2', 'position', 'text', "E-Com Manager, L'Oreal LDB Division", "مدير التجارة الإلكترونية، قسم L'Oreal LDB", null, 59];

        $rows[] = ['advertise-brands', 'testimonial_3', 'quote', 'text', "'We partnered with noon to lead our advertising efforts for the launch of our flagship device. The campaign not only gave us significant visibility offline & onsite but helped cement our partnership by reinforcing noon as a key destination for Motorola products. The results are evident in the traffic generated, word of mouth & record sales number that we have witnessed so far'", "'تعاونا مع نون لقيادة إعلاناتنا لإطلاق جهازنا الرئيسي. لم تمنح الحملة لنا فقط رؤية ملحوظة في الموقع وخارجه، بل ساعدت أيضًا في ترسيخ شراكتنا من خلال تعزيز نون كوجهة أساسية لمنتجات موتورولا. كانت النتائج واضحة في رفع حركة المرور، والترويج الشفوي، وأرقام المبيعات القياسية التي شهدناها حتى الآن.'", null, 60];
        $rows[] = ['advertise-brands', 'testimonial_3', 'name', 'text', 'Vinayak Shenoy', 'فيناياك شينوي', null, 61];
        $rows[] = ['advertise-brands', 'testimonial_3', 'position', 'text', 'Marketing Director, Motorola Mobiles', 'مدير التسويق بشركة موتورولا للهواتف المحمولة', null, 62];

        // ─── page_key = 'advertise-advertisers' ────────────────────────────
        $rows[] = ['advertise-advertisers', 'meta', 'title', 'text', 'Popular Ad Solutions | Advertisers - noon', 'حلول الإعلانات ذات الشعبية | المعلنين - نون', null, 1];
        $rows[] = ['advertise-advertisers', 'meta', 'description', 'text', 'Not selling on noon, but looking to boost your visibility to large audiences across geographies? Leverage our ad solutions to reach your targeted customers.', 'لا تبيع على نون، ولكنك تتطلع إلى تعزيز ظهورك لجماهير فئة كبيرة عبر المناطق الجغرافية؟ استفد من حلولنا الإعلانية للوصول إلى عملائك المستهدفين.', null, 2];

        $rows[] = ['advertise-advertisers', 'hero', 'image', 'image', 'Advertisers', 'المعلنين', 'https://advertise.noon.com/images/advertiser-home-ar.png', 10];
        $rows[] = ['advertise-advertisers', 'hero', 'cta_button', 'link', 'Contact us', 'اتصل بنا', null, 11];
        $rows[] = ['advertise-advertisers', 'hero', 'eyebrow', 'text', 'Advertisers', 'المعلنين', null, 12];
        $rows[] = ['advertise-advertisers', 'hero', 'subtitle', 'text', 'Not selling on noon, but looking to boost your visibility to large audiences across geographies? Want to increase awareness of specific events/ launches? Planning on sharing specific offers to targeted customers?', "'لا تبيع على نون، ولكنك تتطلع إلى تعزيز ظهورك لجماهير فئة كبيرة عبر المناطق الجغرافية؟ هل ترغب في زيادة الوعي بأحداث/إطلاقات محددة؟ هل تخطط لمشاركة عروض محددة للعملاء المستهدفين؟'", null, 13];
        $rows[] = ['advertise-advertisers', 'hero', 'solutions_title', 'text', 'Popular Ad Solutions', 'حلول الإعلانات ذات الشعبية', null, 14];

        $rows[] = ['advertise-advertisers', 'solutions_item_1', 'title', 'text', 'Managed Display Ads', 'إدارة العرض', null, 20];
        $rows[] = ['advertise-advertisers', 'solutions_item_1', 'description', 'text', 'Obtain additional support from noon ads specialists to access premium onsite placements and reach wider audiences with the support of our specialist.', 'احصل على دعم إضافي من متخصصي إعلانات نون للوصول إلى مواضع متميزة في الموقع والوصول إلى جماهير أوسع بدعم من متخصصينا', null, 21];
        $rows[] = ['advertise-advertisers', 'solutions_item_1', 'link', 'link', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', null, 22];
        $rows[] = ['advertise-advertisers', 'solutions_item_1', 'image', 'image', 'Managed Display Ads', 'إدارة العرض', 'https://advertise.noon.com/images/managedDisplayAds_ar.png', 23];

        $rows[] = ['advertise-advertisers', 'solutions_item_2', 'title', 'text', 'Offsite Solutions', 'حلول خارج الموقع', null, 24];
        $rows[] = ['advertise-advertisers', 'solutions_item_2', 'description', 'text', 'Attract millions of eyes with high impact OOH solutions strategically placed in high-traffic locations tailored to your ideal audience or go straight to their doorsteps with personalized and targeted BTL marketing.', 'قم بجذب الملايين من المتسوقين من خلال حلول الإعلان الخارجي ذات التأثير العالي والتي تم وضعها بشكل استراتيجي في مواقع ذات حركة مرور كبيرة، وهي مصممة خصيصًا لجمهورك المستهدف. أو يمكنك أن تذهب مباشرة إلىيهم عبر تسويق تحت الخط (BTL) الشخصي والمستهدف.', null, 25];
        $rows[] = ['advertise-advertisers', 'solutions_item_2', 'link', 'link', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', null, 26];
        $rows[] = ['advertise-advertisers', 'solutions_item_2', 'image', 'image', 'Offsite Solutions', 'حلول خارج الموقع', 'https://advertise.noon.com/images/holdingImage2.png', 27];

        $rows[] = ['advertise-advertisers', 'solutions_item_3', 'title', 'text', 'Sponsorship Opportunities', 'فرص الرعاية', null, 28];
        $rows[] = ['advertise-advertisers', 'solutions_item_3', 'description', 'text', 'Take part in anticipated events and offerings to benefit from exponential traffic giving your brand extra visibility both online and offline. Partner with us and position your brand at the forefront of platform-wide/ category events and impactful collaborations.', 'شارك في الأحداث والعروض المرتقبة للاستفادة من البيانات مما يمنح علامتك التجارية رؤية إضافية سواء عبر الإنترنت أو خارجه. كن شريكًا معنا وضع علامتك التجارية في طليعة الأحداث على مستوى النظام الأساسي/الفئة وعمليات التعاون المؤثرة.', null, 29];
        $rows[] = ['advertise-advertisers', 'solutions_item_3', 'link', 'link', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', null, 30];
        $rows[] = ['advertise-advertisers', 'solutions_item_3', 'image', 'image', 'Sponsorship Opportunities', 'فرص الرعاية', 'https://advertise.noon.com/images/sponsored.png', 31];

        $rows[] = ['advertise-advertisers', 'solutions_item_4', 'title', 'text', 'CRM and Social Media', 'إدارة العلاقات مع العملاء ووسائل التواصل الاجتماعي', null, 32];
        $rows[] = ['advertise-advertisers', 'solutions_item_4', 'description', 'text', 'Engage your audience with targeted push notifications and social media campaigns leveraging our wide network of marketers and influencers.', 'تفاعل مع جمهورك من خلال إشعارات الدفع المستهدفة وحملات وسائل التواصل الاجتماعي باستخدام شبكتنا الواسعة من المسوقين والمؤثرين.', null, 33];
        $rows[] = ['advertise-advertisers', 'solutions_item_4', 'link', 'link', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', null, 34];
        $rows[] = ['advertise-advertisers', 'solutions_item_4', 'image', 'image', 'CRM and Social Media', 'إدارة العلاقات مع العملاء ووسائل التواصل الاجتماعي', 'https://advertise.noon.com/images/crm.png', 35];

        // ─── page_key = 'advertise-display' ────────────────────────────────
        $rows[] = ['advertise-display', 'meta', 'title', 'text', 'Display Ads | noon', 'إعلانات العرض | نون', null, 1];
        $rows[] = ['advertise-display', 'meta', 'description', 'text', 'Highlight specific campaigns you want to push, including new launches, clearance items, or seasonal offerings to target audiences likely to be interested.', 'قم بتسليط الضوء على الحملات المحددة التي ترغب في الترويج لها، بما في ذلك عمليات الإطلاق الجديدة أو منتجات التصفية أو العروض الموسمية لاستهداف الجماهير التي من المحتمل أن تكون مهتمة.', null, 2];

        $rows[] = ['advertise-display', 'hero_feature_1', 'label', 'text', 'Diversified Audience', 'جمهور متنوع', null, 10];
        $rows[] = ['advertise-display', 'hero_feature_2', 'label', 'text', 'Premium Placements', 'التموضع المميز', null, 11];
        $rows[] = ['advertise-display', 'hero_feature_3', 'label', 'text', 'Increased Visibility', 'زيادة الرؤية', null, 12];
        $rows[] = ['advertise-display', 'hero', 'title', 'text', 'Display Ads', 'إعلانات العرض', null, 13];
        $rows[] = ['advertise-display', 'hero', 'subtitle', 'text', 'Highlight specific campaigns you want to push, including new launches, clearance items, or seasonal offerings to target audiences likely to be interested.', 'قم بتسليط الضوء على الحملات المحددة التي ترغب في الترويج لها، بما في ذلك عمليات الإطلاق الجديدة أو منتجات التصفية أو العروض الموسمية لاستهداف الجماهير التي من المحتمل أن تكون مهتمة.', null, 14];
        $rows[] = ['advertise-display', 'hero', 'cta_button', 'text', 'Start now', 'ابدأ الآن', null, 15];
        $rows[] = ['advertise-display', 'hero', 'image', 'image', 'Display Ads', 'إعلانات العرض', 'https://advertise.noon.com/images/display-ads-home_ar.png', 16];

        $rows[] = ['advertise-display', 'quick_guide', 'title', 'text', 'Quick Start Guide', 'دليل البدء السريع', null, 20];
        $rows[] = ['advertise-display', 'quick_guide', 'rewind_alt', 'text', 'Skip backward 10 seconds', 'إرجاع 10 ثوانٍ', null, 21];
        $rows[] = ['advertise-display', 'quick_guide', 'play_pause_label', 'text', 'Play / Pause', 'تشغيل / إيقاف', null, 22];
        $rows[] = ['advertise-display', 'quick_guide', 'play_alt', 'text', 'Play', 'تشغيل', null, 23];
        $rows[] = ['advertise-display', 'quick_guide', 'forward_alt', 'text', 'Skip forward 10 seconds', 'تقديم 10 ثوانٍ', null, 24];
        $rows[] = ['advertise-display', 'quick_guide', 'mute_label', 'text', 'Mute', 'كتم الصوت', null, 25];

        $rows[] = ['advertise-display', 'faq', 'title', 'text', 'Frequently asked questions', 'الأسئلة الشائعة', null, 30];

        // ─── page_key = 'advertise-product' ────────────────────────────────
        $rows[] = ['advertise-product', 'meta', 'title', 'text', 'Product Ads | noon', 'إعلانات المنتجات | نون', null, 1];
        $rows[] = ['advertise-product', 'meta', 'description', 'text', 'Amplify your products visibility on the lower funnel with targeted ads that reach a larger customer base and enable growth.', 'قم بتعزيز رؤية منتجاتك على مسار التحويل السفلي من خلال الإعلانات المستهدفة التي تصل إلى قاعدة عملاء أكبر وتمكّن النمو.', null, 2];

        $rows[] = ['advertise-product', 'hero_feature_1', 'label', 'text', 'Targeted audience', 'الجمهور المستهدف', null, 10];
        $rows[] = ['advertise-product', 'hero_feature_2', 'label', 'text', 'Product visibility', 'رؤية المنتج', null, 11];
        $rows[] = ['advertise-product', 'hero_feature_3', 'label', 'text', 'Increase conversion', 'زيادة التحويل', null, 12];
        $rows[] = ['advertise-product', 'hero', 'eyebrow', 'text', 'Product Ads', 'إعلانات المنتجات', null, 13];
        $rows[] = ['advertise-product', 'hero', 'title', 'text', 'Product Ads', 'إعلانات المنتجات', null, 14];
        $rows[] = ['advertise-product', 'hero', 'subtitle', 'text', 'Amplify your products visibility on the lower funnel with targeted ads that reach a larger customer base and enable growth.', 'قم بتعزيز رؤية منتجاتك على مسار التحويل السفلي من خلال الإعلانات المستهدفة التي تصل إلى قاعدة عملاء أكبر وتمكّن النمو.', null, 15];
        $rows[] = ['advertise-product', 'hero', 'cta_button', 'text', 'Start now', 'ابدأ الآن', null, 16];
        $rows[] = ['advertise-product', 'hero', 'image', 'image', 'Product Ads', 'إعلانات المنتجات', 'https://advertise.noon.com/images/productAdsMain_ar.png', 17];

        $rows[] = ['advertise-product', 'quick_guide', 'title', 'text', 'Quick Start Guide', 'دليل البدء السريع', null, 20];
        $rows[] = ['advertise-product', 'quick_guide', 'rewind_alt', 'text', 'Skip backward 10 seconds', 'إرجاع 10 ثوانٍ', null, 21];
        $rows[] = ['advertise-product', 'quick_guide', 'play_pause_label', 'text', 'Play / Pause', 'تشغيل / إيقاف', null, 22];
        $rows[] = ['advertise-product', 'quick_guide', 'play_alt', 'text', 'Play', 'تشغيل', null, 23];
        $rows[] = ['advertise-product', 'quick_guide', 'forward_alt', 'text', 'Skip forward 10 seconds', 'تقديم 10 ثوانٍ', null, 24];
        $rows[] = ['advertise-product', 'quick_guide', 'mute_label', 'text', 'Mute', 'كتم الصوت', null, 25];

        $rows[] = ['advertise-product', 'faq', 'title', 'text', 'Frequently asked questions', 'الأسئلة الشائعة', null, 30];

        // product-listings.blade.php
        $rows[] = ['advertise-product', 'listings', 'heading', 'text', null, 'جهز قوائم منتجاتك', null, 40];
        $rows[] = ['advertise-product', 'listings', 'heading_en_plain', 'text', 'Get Your', null, null, 41];
        $rows[] = ['advertise-product', 'listings', 'heading_en_highlight', 'text', 'Listings Ready', null, null, 42];
        $rows[] = ['advertise-product', 'listings', 'catalog_image', 'image', 'A noon delivery agent scanning a package', 'مندوب توصيل نون', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-listings-ready.jpg', 43];
        $rows[] = ['advertise-product', 'listings', 'catalog_title', 'text', "Listing your products on noon is easy with our powerful 'My catalog' tool.", 'إدراج منتجاتك على نون سهل جداً مع أداة "كتالوجي" القوية.', null, 44];
        $rows[] = ['advertise-product', 'listings', 'catalog_subtitle', 'text', 'Enriching your catalog boosts discovery and conversion of your products.', 'إثراء الكتالوج الخاص بك يعزز اكتشاف منتجاتك ويزيد من نسبة التحويل.', null, 45];
        $rows[] = ['advertise-product', 'listings', 'upload_options_title', 'text', 'Upload options:', 'خيارات التحميل:', null, 46];
        $rows[] = ['advertise-product', 'listings', 'upload_option_1', 'text', 'Single SKU — for curated catalogues', 'منتج واحد — للكتالوجات المنسقة', null, 47];
        $rows[] = ['advertise-product', 'listings', 'upload_option_2', 'text', 'Bulk upload — for larger catalogues', 'التحميل الجماعي — للكتالوجات الكبيرة', null, 48];
        $rows[] = ['advertise-product', 'listings', 'upload_option_3', 'text', 'API — for automated systems', 'API — للأنظمة المؤتمتة', null, 49];
        $rows[] = ['advertise-product', 'listings', 'catalog_footnote', 'text', 'You can also set pricing and update stock directly from My Catalogue — all in one place', 'يمكنك أيضًا تحديد الأسعار وتحديث المخزون مباشرة من كتالوجي — كل ذلك في مكان واحد', null, 50];
        $rows[] = ['advertise-product', 'listings', 'new_window_title', 'text', 'Opens in a new window', 'يفتح في نافذة جديدة', null, 51];
        $rows[] = ['advertise-product', 'listings', 'tutorials_link', 'link', 'Watch The Tutorials', 'شاهد فيديوهات الشرح', 'https://www.youtube.com/@noonsellerlab7442/videos', 52];

        $rows[] = ['advertise-product', 'listings', 'brand_details_title', 'text', 'Brand details', 'تفاصيل العلامة التجارية', null, 53];
        $rows[] = ['advertise-product', 'listings', 'brand_details_image', 'image', 'Toys on top of a cabinet', 'ألعاب على رف', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-brand-details.jpg', 54];
        $rows[] = ['advertise-product', 'listings', 'brand_own_eyebrow', 'text', 'Own a brand or represent one?', 'تملك علامة تجارية أو تمثل واحدة؟', null, 55];
        $rows[] = ['advertise-product', 'listings', 'brand_registry_intro', 'text', 'noon\'s brand registry gives you the tools to protect your intellectual property rights and build trust with customers.', 'يمنحك سجل العلامات التجارية في نون الأدوات لحماية حقوق الملكية الفكرية وبناء الثقة مع العملاء.', null, 56];
        $rows[] = ['advertise-product', 'listings', 'authorised_agent_title', 'text', 'Authorised agent?', 'وكيل معتمد؟', null, 57];
        $rows[] = ['advertise-product', 'listings', 'authorised_agent_subtitle', 'text', 'Send us the right documents to get started:', 'أرسل لنا المستندات الصحيحة للبدء:', null, 58];
        $rows[] = ['advertise-product', 'listings', 'brand_doc_1', 'text', 'Brand authorisation letter', 'خطاب تفويض العلامة التجارية', null, 59];
        $rows[] = ['advertise-product', 'listings', 'brand_doc_2', 'text', 'Manufacturer licence', 'ترخيص المصنع', null, 60];
        $rows[] = ['advertise-product', 'listings', 'brand_doc_3', 'text', 'Product or brand registration documents', 'مستندات تسجيل المنتج أو العلامة التجارية', null, 61];
        $rows[] = ['advertise-product', 'listings', 'distributor_title', 'text', 'Distributor?', 'موزع؟', null, 62];
        $rows[] = ['advertise-product', 'listings', 'distributor_subtitle', 'text', 'Skip ahead to product and category information.', 'انتقل مباشرة إلى معلومات المنتج والفئة.', null, 63];
        $rows[] = ['advertise-product', 'listings', 'already_applied_title', 'text', 'Already applied?', 'قدمت طلبك؟', null, 64];
        $rows[] = ['advertise-product', 'listings', 'already_applied_subtitle', 'text', "Sit tight — wait until your brand registration is complete before adding products, to make sure they're listed under the right brand.", 'خلّك مستعد — انتظر حتى يتم الانتهاء من تسجيل علامتك التجارية قبل البدء في إضافة المنتجات لضمان عرضها تحت العلامة التجارية الصحيحة.', null, 65];
        $rows[] = ['advertise-product', 'listings', 'brand_registry_link', 'link', 'Read More On Brand Registry', 'اقرأ المزيد عن سجل العلامات التجارية', null, 66];

        $rows[] = ['advertise-product', 'listings', 'category_info_image', 'image', 'A noon employee walking through the warehouse', 'موظف نون في المستودع', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-category-info.jpg', 67];
        $rows[] = ['advertise-product', 'listings', 'category_info_title', 'text', 'Product and category information', 'معلومات المنتج والفئة', null, 68];
        $rows[] = ['advertise-product', 'listings', 'category_info_subtitle', 'text', "Prepare your product and category information before you start adding products — it'll save you time and help you avoid mistakes.", 'حضر معلومات المنتج والفئة قبل ما تبدأ في إضافة المنتجات — سيوفر لك الوقت ويجنبك الأخطاء.', null, 69];
        $rows[] = ['advertise-product', 'listings', 'heads_up_title', 'text', 'Heads up:', 'تنبيه:', null, 70];
        $rows[] = ['advertise-product', 'listings', 'heads_up_subtitle', 'text', 'Some categories require approval first, including document checks and seller performance review.', 'بعض الفئات تتطلب الموافقة أولًا، بما في ذلك التحقق من المستندات وأداء البائع.', null, 71];
        $rows[] = ['advertise-product', 'listings', 'category_guide_link', 'link', 'Read The Guide', 'اقرأ الدليل', null, 72];

        // ─── page_key = 'advertise-request' ────────────────────────────────
        $rows[] = ['advertise-request', 'meta', 'title', 'text', "Let's start a conversation | Contact us - noon", 'لنبدأ محادثة | اتصل بنا - نون', null, 1];
        $rows[] = ['advertise-request', 'meta', 'description', 'text', 'Leverage our ad solutions today to reach your marketing or sales objectives across locations and irrespective of your budget.', 'استفد من حلولنا الإعلانية اليوم للوصول إلى أهدافك التسويقية أو البيعية عبر المواقع بغض النظر عن ميزانيتك.', null, 2];

        $rows[] = ['advertise-request', 'form', 'icon', 'image', 'Contact us', 'اتصل بنا', 'https://advertise.noon.com/images/contactUs.png', 10];
        $rows[] = ['advertise-request', 'form', 'eyebrow', 'text', 'Contact us', 'اتصل بنا', null, 11];
        $rows[] = ['advertise-request', 'form', 'title', 'text', "Let's start a conversation", 'لنبدأ محادثة', null, 12];
        $rows[] = ['advertise-request', 'form', 'subtitle', 'text', 'Leverage our ad solutions today to reach your marketing or sales objectives across locations and irrespective of your budget.', 'استفد من حلولنا الإعلانية اليوم للوصول إلى أهدافك التسويقية أو البيعية عبر المواقع بغض النظر عن ميزانيتك.', null, 13];
        $rows[] = ['advertise-request', 'form', 'success_message', 'text', 'Your request has been accepted. We will contact you soon.', 'تم قبول طلبك. سيتم التواصل معك قريبًا.', null, 14];
        $rows[] = ['advertise-request', 'form', 'error_message', 'text', 'An error occurred. Please try again later.', 'حدث خطأ ما. يرجى المحاولة مرة أخرى لاحقًا.', null, 15];
        $rows[] = ['advertise-request', 'form', 'name_label', 'text', 'Name', 'الاسم', null, 16];
        $rows[] = ['advertise-request', 'form', 'name_placeholder', 'text', 'Please enter your full name!', 'يرجى إدخال اسمك الكامل!', null, 17];
        $rows[] = ['advertise-request', 'form', 'email_label', 'text', 'Email', 'البريد الإلكتروني', null, 18];
        $rows[] = ['advertise-request', 'form', 'email_placeholder', 'text', 'Please enter a valid email address!', 'يرجى إدخال عنوان بريد إلكتروني صالح!', null, 19];
        $rows[] = ['advertise-request', 'form', 'company_label', 'text', 'Company Name', 'اسم الشركة', null, 20];
        $rows[] = ['advertise-request', 'form', 'company_placeholder', 'text', 'Please enter your company name!', 'يرجى إدخال اسم شركتك!', null, 21];
        $rows[] = ['advertise-request', 'form', 'phone_label', 'text', 'Phone Number (optional)', 'رقم الجوال (اختياري)', null, 22];
        $rows[] = ['advertise-request', 'form', 'phone_placeholder', 'text', 'Please enter your phone number with country code. eg: 9715XXXXXXXX', 'أدخل رقم جوالك مع رمز الدولة، مثال: 9715XXXXXXXX', null, 23];
        $rows[] = ['advertise-request', 'form', 'description_label', 'text', 'Description', 'الوصف', null, 24];
        $rows[] = ['advertise-request', 'form', 'description_placeholder', 'text', 'Please enter your request details in the description!', 'يرجى إدخال تفاصيل الطلب في الوصف!', null, 25];
        $rows[] = ['advertise-request', 'form', 'submit_button', 'text', 'Submit', 'قدّم', null, 26];

        // ─── page_key = 'advertise_footer' ─────────────────────────────────
        $rows[] = ['advertise_footer', 'main', 'copyright', 'text', 'noon. All Rights Reserved', 'نون. جميع الحقوق محفوظة', null, 1];
        $rows[] = ['advertise_footer', 'main', 'sell_with_us', 'link', 'Sell with us', 'بيع معنا', null, 2];
        $rows[] = ['advertise_footer', 'main', 'terms_of_use', 'link', 'Terms of Use', 'شروط الاستخدام', 'https://www.noon.com/uae-en/terms-of-use/', 3];
        $rows[] = ['advertise_footer', 'main', 'terms_of_sale', 'link', 'Terms of Sale', 'شروط البيع', 'https://www.noon.com/uae-en/terms-of-sale/', 4];
        $rows[] = ['advertise_footer', 'main', 'privacy_policy', 'link', 'Privacy Policy', 'سياسة الخصوصية', 'https://www.noon.com/uae-en/privacy-policy/', 5];

        foreach ($rows as [$pageKey, $blockKey, $fieldKey, $type, $valueEn, $valueAr, $valueUrl, $sortOrder]) {
            PortalContent::updateOrCreate(
                ['page_key' => $pageKey, 'block_key' => $blockKey, 'field_key' => $fieldKey],
                [
                    'type' => $type,
                    'value_en' => $valueEn,
                    'value_ar' => $valueAr,
                    'value_url' => $valueUrl,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]
            );
        }

        PortalContent::flush('advertise-brands');
        PortalContent::flush('advertise-advertisers');
        PortalContent::flush('advertise-display');
        PortalContent::flush('advertise-product');
        PortalContent::flush('advertise-request');
        PortalContent::flush('advertise_footer');
    }
}
