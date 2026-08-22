<?php

namespace Database\Seeders;

use App\Models\PortalContent;
use Illuminate\Database\Seeder;

/**
 * Batch 1 follow-up to PortalContentSeeder: seeds the "Portal Content" CMS
 * rows for the sellers, how-it-works, fulfillment, and smart-tools pages
 * (and the small teaser blocks those pages' partials contribute to the
 * 'home' page_key), converted via portal_content()/portal_link()/portal_image()
 * in resources/views/portal/*.blade.php.
 *
 * See PortalContentSeeder.php's doc comment for the full pattern. Every
 * value_en/value_ar below is exactly the hardcoded copy that was live in
 * the blade files before conversion, so admins start from today's text.
 */
class PortalContentSeederBatch1 extends Seeder
{
    public function run(): void
    {
        $rows = [];

        // ─── page_key = 'sellers' (sellers.blade.php + partials) ───────────
        $rows[] = ['sellers', 'hero', 'eyebrow', 'text', 'Sellers', 'البائعين', null, 1];
        $rows[] = ['sellers', 'hero', 'subtitle', 'text',
            'Join thousands of sellers leveraging our ad solutions to reach their marketing and sales objectives across locations and irrespective of their budgets.',
            'انضم إلى آلاف البائعين الذين يستفيدون من حلولنا الإعلانية لتحقيق أهدافهم التسويقية والمبيعاتية عبر المواقع بغض النظر عن ميزانياتهم.', null, 2];
        $rows[] = ['sellers', 'hero', 'cta_button', 'link', 'Start now', 'ابدأ الآن', '/register', 3];
        $rows[] = ['sellers', 'hero', 'photo', 'image', 'Sellers', 'البائعين', 'https://advertise.noon.com/images/sellers-home.png', 4];
        $rows[] = ['sellers', 'hero', 'title', 'text', 'Popular Ad Solutions', 'حلول الإعلانات ذات الشعبية', null, 5];

        $rows[] = ['sellers', 'solution_product_ads', 'title', 'text', 'Product Ads', 'إعلانات المنتجات', null, 10];
        $rows[] = ['sellers', 'solution_product_ads', 'description', 'text',
            "Amplify your products visibility on the lower funnel with targeted ads that reach a larger customer base and enable growth. This feature will significantly increase product visibility, customer reach and conversion potential.",
            'قم بتعزيز رؤية منتجاتك في الأسفل باستخدام الإعلانات المستهدفة التي تصل إلى قاعدة عملاء أكبر وتمكّن النمو. سيعمل هذا المنتج على زيادة ظهور منتجاتك بشكل كبير، والوصول إلى العملاء، وإمكانات التحويل.', null, 11];
        $rows[] = ['sellers', 'solution_product_ads', 'link_label', 'text', 'Learn More', 'اعرف أكثر', null, 12];

        $rows[] = ['sellers', 'solution_display_ads', 'title', 'text', 'Display Ads', 'إعلانات العرض', null, 20];
        $rows[] = ['sellers', 'solution_display_ads', 'description', 'text',
            'Highlight specific campaigns you want to push, including new launches, clearance items, or seasonal offerings to target audiences likely to be interested.',
            'قم بتسليط الضوء على الحملات المحددة التي ترغب في الترويج لها، بما في ذلك عمليات الإطلاق الجديدة أو منتجات التصفية أو العروض الموسمية، لاستهداف الجماهير التي من المحتمل أن تكون مهتمة.', null, 21];
        $rows[] = ['sellers', 'solution_display_ads', 'link_label', 'text', 'Learn More', 'اعرف أكثر', null, 22];

        $rows[] = ['sellers', 'solution_crm_social', 'title', 'text', 'CRM and Social Media', 'إدارة العلاقات مع العملاء ووسائل التواصل الاجتماعي', null, 30];
        $rows[] = ['sellers', 'solution_crm_social', 'description', 'text',
            'Engage your audience with targeted push notifications and social media campaigns leveraging our wide network of marketers and influencers.',
            'تواصل مع جمهورك من خلال إشعارات الدفع المستهدفة وحملات وسائل التواصل الاجتماعي باستخدام شبكتنا الواسعة من المسوقين والمؤثرين.', null, 31];
        $rows[] = ['sellers', 'solution_crm_social', 'link_label', 'text', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', null, 32];

        $rows[] = ['sellers', 'solution_brand_ads', 'title', 'text', 'Brand Ads', 'إعلانات العلامة التجارية', null, 40];
        $rows[] = ['sellers', 'solution_brand_ads', 'description', 'text',
            "Promote your products and brand in a visually appealing and prominent way within noon's browse, search, and relevant product detail pages. Display multiple products within one ad, showcasing a wider range of offerings and potentially attracting a broader audience.",
            'قم بالترويج لمنتجاتك وعلامتك التجارية بطريقة بصرية جذابة وبارزة داخل صفحات نون للتصفح، البحث، وصفحات تفاصيل المنتج ذات الصلة. بإمكانك أيضا عرض منتجات متعددة داخل إعلان واحد، وإبراز مجموعة واسعة من منتجاتك وجذب جمهور أكبر.', null, 41];
        $rows[] = ['sellers', 'solution_brand_ads', 'link_label', 'text', 'Learn More', 'اعرف أكثر', null, 42];

        $rows[] = ['sellers', 'testimonials', 'eyebrow', 'text', 'Hear from our satisfied customers', 'استمع إلى آراء عملائنا', null, 50];

        $rows[] = ['sellers', 'testimonial_1', 'quote', 'text',
            "'Noon Ads played an instrumental role in enhancing our activities with Noon. Our investments have had, and continue to have, a material and positive impact to grow our sales and achieve our mutual business goals. We continue to see Noon as a key player in the e-commerce industry in the region. This is underscored by the high degree of support and dedication by the entire Noon team.'",
            "'لعبت إعلانات نون دوراً فعالاً في تعزيز أنشطتنا مع نون. استثماراتنا كان ولا تزال، تأثيرها مادياً وإيجابياً في تنمية مبيعاتنا وتحقيق أهدافنا التجارية المتبادلة. لا زلنا نرى نون وجهة رئيسية في عالم التجارة الإلكترونية في المنطقة. وهذا ما يؤكده المستوى العالي من الدعم والتفاني الذي يقدمه فريق نون بأكمله.'", null, 51];
        $rows[] = ['sellers', 'testimonial_1', 'name', 'text', 'Tayyam Katbe', 'تيام كاتبي', null, 52];
        $rows[] = ['sellers', 'testimonial_1', 'position', 'text', 'Senior Executive, Unicharm Gulf Hygienic Industries', 'مدير تنفيذي أول، شركة يونيتشارم جلف لصناعات الصحة', null, 53];

        $rows[] = ['sellers', 'testimonial_2', 'quote', 'text',
            "'We partnered with noon to lead our advertising efforts for the launch of our products. The campaign not only gave us significant visibility in offline & onsite but helped cement our partnership by reinforcing noon as a key destination for funmoment products. The results are evident in the traffic generated, word of mouth & record sales number that we have witnessed so far.'",
            "'لقد عقدنا شراكة مع نون لقيادة جهودنا الإعلانية لإطلاق منتجاتنا. لم تمنحنا الحملة رؤية كبيرة في الموقع وخارجي فحسب، بل ساعدت في تعزيز شراكتنا من خلال تعزيز نون وجهة رئيسية لمنتجات لحظة فرح. وتتجلى النتائج في عدد الزيارات والكلمات الشفوية وأرقام المبيعات القياسية التي شهدناها حتى الآن.'", null, 54];
        $rows[] = ['sellers', 'testimonial_2', 'name', 'text', 'Salem Habtoor', 'سالم حبتور', null, 55];
        $rows[] = ['sellers', 'testimonial_2', 'position', 'text', 'Direct manager, Fun moment', 'المدير المباشر، لحظة متعة', null, 56];

        $rows[] = ['sellers', 'testimonial_3', 'quote', 'text',
            "'Our experience collaborating with Noon on their website advertising services has been exceptional. From the moment we engaged with their team, we noticed a significant boost in our brand visibility and sales performance. Collaborating with Noon has not only improved our sales but also created a sustainable brand presence that resonates with customers.'",
            "'لقد كانت تجربتنا في التعامل مع نون في خدمات الإعلان على موقعهم الإلكتروني استثنائية. منذ اللحظة التي تعاملنا فيها مع فريقهم، لاحظنا زيادة كبيرة في رؤية علامتنا التجارية وأداء المبيعات. إن التعامل مع نون لم يؤدِ إلى تحسين مبيعاتنا فحسب، بل أدى أيضاً إلى خلق حضور مستدام للعلامة التجارية وتردد صداه مع العملاء.'", null, 57];
        $rows[] = ['sellers', 'testimonial_3', 'name', 'text', 'Hassan Ghattas', 'حسن غطاس', null, 58];
        $rows[] = ['sellers', 'testimonial_3', 'position', 'text', 'Marketing Manager, Abdul Wahed', 'مدير التسويق عبد الواحد', null, 59];

        // ─── page_key = 'how-it-works' (how-it-works.blade.php + partials) ─
        $rows[] = ['how-it-works', 'hero', 'title_line1', 'text', 'Welcome to', 'مرحبا بك في', null, 1];
        $rows[] = ['how-it-works', 'hero', 'title_line2', 'text', 'selling on noon!', 'البيع على نون!', null, 2];
        $rows[] = ['how-it-works', 'hero', 'subtitle', 'text',
            'Turn clicks into cash. Start strong by listing your products the right way — and watch your sales take off.',
            'حول النقرات إلى أموال. ابدأ بقوة من خلال إدراج منتجاتك بالطريقة الصحيحة — وشاهد مبيعاتك تنطلق.', null, 3];
        $rows[] = ['how-it-works', 'hero', 'photo', 'image', 'noon employees working', 'موظفو نون في العمل', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-hero.jpg', 4];

        $rows[] = ['how-it-works', 'why-sell', 'title', 'text', 'Why Join Us?', 'لماذا تنضم إلينا؟', null, 10];
        $rows[] = ['how-it-works', 'why-sell-item-1', 'title', 'text', 'Reach Millions', 'وصل الملايين', null, 11];
        $rows[] = ['how-it-works', 'why-sell-item-1', 'description', 'text', 'Millions of shoppers, one app. noon puts your products in front of more people, every single day.', 'ملايين المتسوقين، تطبيق واحد. نون تعرض منتجاتك لعدد أكبر من الناس، كل يوم.', null, 12];
        $rows[] = ['how-it-works', 'why-sell-item-2', 'title', 'text', 'Fast, Flexible Delivery', 'توصيل سريع ومرن', null, 13];
        $rows[] = ['how-it-works', 'why-sell-item-2', 'description', 'text', 'Choose how you ship. noon handles the speed, care, and customer smiles.', 'اختر طريقة الشحن التي تناسبك. نون تهتم بالسرعة، العناية، ورضا العملاء.', null, 14];
        $rows[] = ['how-it-works', 'why-sell-item-3', 'title', 'text', 'Grow Fast, Earn More', 'نمِّ أعمالك بسرعة، واربح أكثر', null, 15];
        $rows[] = ['how-it-works', 'why-sell-item-3', 'description', 'text', "Unlock growth with noon's seller tools — built to turn your hustle into real results.", 'حقق النمو مع أدوات البيع من نون — صُممت لتحوّل شغفك إلى نتائج حقيقية.', null, 16];

        $rows[] = ['how-it-works', 'steps', 'title_prefix', 'text', 'Steps to ', 'خطوات لـ ', null, 20];
        $rows[] = ['how-it-works', 'steps', 'title_highlight', 'text', 'Go Live', 'البدء', null, 21];
        $rows[] = ['how-it-works', 'steps', 'photo', 'image', 'noon Employees packing items into crates', 'موظفو نون يعبئون الصناديق', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-steps-go-live.jpg', 22];
        $rows[] = ['how-it-works', 'steps', 'subtitle', 'text', 'Start selling on noon in three easy steps', 'ابدأ البيع على نون بثلاث خطوات سهلة', null, 23];
        $rows[] = ['how-it-works', 'steps', 'step_1', 'text', 'Set up your account', 'قم بإعداد حسابك', null, 24];
        $rows[] = ['how-it-works', 'steps', 'step_2', 'text', 'Get your listings ready', 'جهز قوائم منتجاتك', null, 25];
        $rows[] = ['how-it-works', 'steps', 'step_3', 'text', 'Choose your fulfilment model', 'اختر نموذج التنفيذ الخاص بك', null, 26];
        $rows[] = ['how-it-works', 'steps', 'closing_note', 'text', "That's it — you're ready to receive your first order", 'هذا كل شيء — أنت مستعد لتلقي أول طلب لك', null, 27];

        $rows[] = ['how-it-works', 'checklist', 'eyebrow', 'text', 'Getting Started', 'البدء', null, 30];
        $rows[] = ['how-it-works', 'checklist', 'title', 'text', 'Ready to start selling?', 'جاهز انك تبدأ البيع؟', null, 31];
        $rows[] = ['how-it-works', 'checklist', 'subtitle', 'text', "It's quick and easy — here's what you'll need", 'انه سريع و سهل — اليك ما تحتاجه', null, 32];
        $rows[] = ['how-it-works', 'checklist', 'list_title', 'text', 'Seller Setup Checklist:', 'قائمة إعداد البائع:', null, 33];
        $rows[] = ['how-it-works', 'checklist', 'item_1', 'text', 'Email address and a phone number', 'عنوان بريد إلكتروني ورقم هاتف', null, 34];
        $rows[] = ['how-it-works', 'checklist', 'item_2', 'text', 'Commercial Registration / Trade License', 'السجل التجاري / رخصة التجارة', null, 35];
        $rows[] = ['how-it-works', 'checklist', 'item_3', 'text', 'Identity proof — Passport / Emirates ID', 'إثبات الهوية — جواز السفر / بطاقة الهوية الإماراتية', null, 36];
        $rows[] = ['how-it-works', 'checklist', 'documents_faq_button', 'link', 'Documents FAQs', 'الأسئلة الشائعة حول المستندات', '/faq', 37];
        $rows[] = ['how-it-works', 'checklist', 'steps_intro', 'text', 'Once you have everything ready, getting started is super simple:', 'بمجرد أن يكون لديك كل شيء جاهزًا، فإن البدء بسيط جدًا:', null, 38];
        $rows[] = ['how-it-works', 'checklist', 'step_1_label', 'text', 'Step 1:', 'الخطوة 1:', null, 39];
        $rows[] = ['how-it-works', 'checklist', 'step_1_text', 'text', 'Set up your account', 'إعداد حسابك', null, 40];
        $rows[] = ['how-it-works', 'checklist', 'step_2_label', 'text', 'Step 2:', 'الخطوة 2:', null, 41];
        $rows[] = ['how-it-works', 'checklist', 'step_2_text', 'text', 'List your products', 'إدراج منتجاتك', null, 42];
        $rows[] = ['how-it-works', 'checklist', 'step_3_label', 'text', 'Step 3:', 'الخطوة 3:', null, 43];
        $rows[] = ['how-it-works', 'checklist', 'step_3_text', 'text', 'Choose your fulfilment model', 'اختر نموذج التنفيذ الخاص بك', null, 44];
        $rows[] = ['how-it-works', 'checklist', 'closing_lead', 'text', "And that's it!", 'و هذا هو كل شئ!', null, 45];
        $rows[] = ['how-it-works', 'checklist', 'closing_text', 'text', ' Your first customer could be just a click away.', ' عميلك الأول قد يكون على بعد نقرة واحدة فقط.', null, 46];
        $rows[] = ['how-it-works', 'checklist', 'learn_more_button', 'link', 'Learn more', 'اعرف أكثر', '/how-it-works', 47];

        $rows[] = ['how-it-works', 'trader_banner', 'alt', 'text', 'Dubai Traders Program', 'برنامج تجار دبي', null, 50];

        $rows[] = ['how-it-works', 'fulfilment-model', 'title', 'text', 'Choose the fulfilment model that fits you', 'اختر نموذج التنفيذ المناسب لك', null, 60];
        $rows[] = ['how-it-works', 'fulfilment-model', 'photo', 'image', 'A noon employee instructing a fleet of delivery vans', 'موظف نون يوجه أسطول توصيل', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-fulfillment-model.jpg', 61];
        $rows[] = ['how-it-works', 'fulfilment-model', 'subtitle', 'text',
            'Covers storage, packing, shipping, returns and exchanges — with great delivery that keeps customers coming back.',
            'يشمل التخزين، التعبئة، الشحن، الإرجاع، والاستبدال — وتوصيل ممتاز يضمن عودة العملاء مرة بعد مرة.', null, 62];
        $rows[] = ['how-it-works', 'fulfilment-model', 'description', 'text',
            'You can fulfil orders yourself (FBP) or leave it to noon with Fulfilled by noon (FBN).',
            'يمكنك تنفيذ الطلبات بنفسك (FBP) أو ترك المهمة لنون من خلال التنفيذ من قِبل نون (FBN).', null, 63];
        $rows[] = ['how-it-works', 'fulfilment-model', 'tip_label', 'text', 'Pro tip:', 'نصيحة احترافية:', null, 64];
        $rows[] = ['how-it-works', 'fulfilment-model', 'tip_text', 'text',
            'You can mix models — pick the best one for each product to optimise your operations.',
            'يمكنك الدمج بين النماذج — اختر أفضل نموذج لكل منتج لتحسين عملياتك.', null, 65];
        $rows[] = ['how-it-works', 'fulfilment-model', 'explainer_videos_button', 'link', 'Watch explainer videos', 'شاهد فيديوهات الشرح', 'https://www.youtube.com/@noonsellerlab7442/videos', 66];
        $rows[] = ['how-it-works', 'fulfilment-model', 'learn_more_button', 'link', 'Learn more', 'اعرف أكثر', '/fulfillment', 67];

        $rows[] = ['how-it-works', 'seller-hub', 'photo', 'image', 'Seller Hub', 'مركز البائعين', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/04-transparent-fees-01.jpg', 70];
        $rows[] = ['how-it-works', 'seller-hub', 'eyebrow', 'text', 'Seller Hub', 'مركز البائعين', null, 71];
        $rows[] = ['how-it-works', 'seller-hub', 'title', 'text', 'Your command centre for selling on noon', 'مركز قيادتك للبيع على نون', null, 72];
        $rows[] = ['how-it-works', 'seller-hub-item-1', 'title', 'text', 'Manage your business', 'قم بادارة اعمالك', null, 73];
        $rows[] = ['how-it-works', 'seller-hub-item-1', 'description', 'text', 'Manage your account, catalogue, inventory and pricing - all in one place.', 'قم بإدارة حسابك، الكتالوج، المخزون، والأسعار - كل ذلك من مكان واحد.', null, 74];
        $rows[] = ['how-it-works', 'seller-hub-item-2', 'title', 'text', 'Track and collect payments', 'تتبع و حصل المدفوعات', null, 75];
        $rows[] = ['how-it-works', 'seller-hub-item-2', 'description', 'text', 'Monitor orders, sales performance, settlements and payouts in real time.', 'راقب الطلبات، أداء المبيعات، التسويات والمدفوعات في الوقت الفعلي.', null, 76];
        $rows[] = ['how-it-works', 'seller-hub-item-3', 'title', 'text', 'Grow with confidence', 'انمو بثقة', null, 77];
        $rows[] = ['how-it-works', 'seller-hub-item-3', 'description', 'text', 'Use ads and analytics to improve performance and scale faster.', 'استخدم الإعلانات والتحليلات لتحسين الأداء والتوسع بشكل أسرع.', null, 78];
        $rows[] = ['how-it-works', 'seller-hub', 'footer_note', 'text',
            "You'll also get our comprehensive seller guide, designed to support you from your first steps to confidently running your business on noon.",
            'ستحصل أيضًا على دليل البائع القوي الخاص بنا، المصمم لدعمك من خطواتك الأولى وحتى إدارة عملك بثقة على نون.', null, 79];

        // ─── page_key = 'fulfillment' (fulfillment.blade.php + partials) ───
        $rows[] = ['fulfillment', 'hero', 'title', 'text', 'Ship Your Way', 'اشحن بطريقتك', null, 1];
        $rows[] = ['fulfillment', 'hero', 'subtitle', 'text', 'Choose how you ship', 'اختر طريقة الشحن المناسبة لك', null, 2];
        $rows[] = ['fulfillment', 'hero', 'description', 'richtext',
            'Fulfilled by noon (FBN) or Fulfilled by Partner (FBP).<br class="hidden md:block"> Either way, we\'ll help you deliver fast.',
            'التنفيذ من قبل نون (FBN) أو التنفيذ من قبل الشريك (FBP). في كل الحالتين، نساعدك على التوصيل بسرعة.', null, 3];
        $rows[] = ['fulfillment', 'hero', 'cta_button', 'link', 'Watch Video', 'شاهد الفيديو', 'https://youtu.be/rm45BkhIBxY', 4];
        $rows[] = ['fulfillment', 'hero', 'photo', 'image', 'A noon warehouse', 'مستودع نون', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/03-hero-092025.jpg', 5];

        $rows[] = ['fulfillment', 'fbn', 'title_prefix', 'text', 'Fulfilled by noon: ', 'التنفيذ من قبل نون: ', null, 10];
        $rows[] = ['fulfillment', 'fbn', 'title_highlight', 'text', 'Built for Speed', 'مصمم للسرعة', null, 11];
        $rows[] = ['fulfillment', 'fbn', 'photo', 'image', 'A noon employee working in the warehouse', 'موظف نون يعمل في المستودع', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/03-fbn.jpg', 12];
        $rows[] = ['fulfillment', 'fbn', 'headline', 'text', 'Customers love fast delivery — and noon express delivers it.', 'العملاء يفضلون التوصيل السريع — ونون إكسبريس يقدمه.', null, 13];
        $rows[] = ['fulfillment', 'fbn', 'description', 'text', 'Store your products in our fulfillment centers, and watch orders take off.', 'خزن منتجاتك في مراكز تنفيذ الطلبات لدينا، وشاهد الطلبات تنطلق.', null, 14];
        $rows[] = ['fulfillment', 'fbn', 'included_label', 'text', "What's included:", 'الإضافة:', null, 15];
        $rows[] = ['fulfillment', 'fbn', 'included_text', 'text',
            'Fast inbound shipping, monthly storage in state-of-the-art facilities, product removal services, and returns processing.',
            'شحن وارد سريع، تخزين شهري في منشآت متطورة، خدمات إزالة المنتجات، ومعالجة المرتجعات.', null, 16];
        $rows[] = ['fulfillment', 'fbn', 'ideal_label', 'text', 'Ideal for:', 'مثالي لـ:', null, 17];
        $rows[] = ['fulfillment', 'fbn', 'ideal_text', 'text', 'Best-selling products, new products, and any seller ready to grow.', 'المنتجات الأكثر مبيعا، المنتجات الجديدة، وكل بائع جاهز للنمو.', null, 18];
        $rows[] = ['fulfillment', 'fbn', 'cta_button', 'link', 'Get started with Fulfilled by noon', 'ابدأ مع التنفيذ من قبل نون', '/register', 19];

        $rows[] = ['fulfillment', 'fbn_testimonial', 'title', 'text', 'Seller success story', 'قصة نجاح بائع', null, 20];
        $rows[] = ['fulfillment', 'fbn_testimonial', 'quote_1', 'text',
            "noon itself has been a huge achievement — I store with them and it's saved me so much at this point",
            'نون بالذات عملت إنجاز كبير أنا أخزن عندها وفرت علي أشياء كثيرة بهذه النقطة', null, 21];
        $rows[] = ['fulfillment', 'fbn_testimonial', 'quote_2', 'text',
            "They took on this cost and eased the burden on me — by God's grace, they played a huge role in the success of my project and my work",
            'هي تحملت التكلفة هذه وخففت العبء عني لعبت دور كبير بفضل الله في أنها نجحت مشروعي ونجحتني في عملي', null, 22];
        $rows[] = ['fulfillment', 'fbn_testimonial', 'author', 'text', 'Aflak Al Thurayya', 'أفلاك الثريا', null, 23];

        $rows[] = ['fulfillment', 'fbp', 'title_prefix', 'text', 'Fulfilled by partner: ', 'التنفيذ من قبل الشريك: ', null, 30];
        $rows[] = ['fulfillment', 'fbp', 'title_highlight', 'text', 'Built for Flexibility', 'مصمم للمرونة', null, 31];
        $rows[] = ['fulfillment', 'fbp', 'photo', 'image', 'noon boxes', 'صناديق نون', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/03-fbp.jpg', 32];
        $rows[] = ['fulfillment', 'fbp', 'headline', 'text', "Some products need a different plan. noon's FBP models give you options:", 'بعض المنتجات تحتاج إلى خطة مختلفة. نماذج FBP من نون تمنحك خيارات:', null, 33];
        $rows[] = ['fulfillment', 'fbp', 'item_1', 'text', 'Direct shipping: You pack, we deliver.', 'الشحن المباشر: أنت تغلّف، ونحن نوصّل.', null, 34];
        $rows[] = ['fulfillment', 'fbp', 'item_2', 'text', 'Direct delivery: Best for high-value products or those that need installation.', 'التوصيل المباشر: الأنسب للمنتجات ذات القيمة العالية أو اللي تحتاج تركيب.', null, 35];
        $rows[] = ['fulfillment', 'fbp', 'ideal_label', 'text', 'Ideal for:', 'مثالي لـ:', null, 36];
        $rows[] = ['fulfillment', 'fbp', 'ideal_text', 'text', 'Heavy, bulky, or specialized products that require a personal touch.', 'المنتجات الثقيلة، الكبيرة، أو المتخصصة التي تتطلب لمسة شخصية.', null, 37];
        $rows[] = ['fulfillment', 'fbp', 'cta_button', 'link', 'Get started with Fulfilled by partner', 'ابدأ مع التنفيذ من قبل الشريك', '/register', 38];

        $rows[] = ['fulfillment', 'fbp_testimonial', 'title', 'text', 'Seller success story', 'قصة نجاح بائع', null, 40];
        $rows[] = ['fulfillment', 'fbp_testimonial', 'quote', 'text',
            'The noon team handles every step – from receiving products, to neatly packaging them, to delivering them to the customer. All you have to do as a seller is focus on your business and manage your store on the platform with ease!',
            'فريق نون بيتكفّل بكل الخطوات – من استلام المنتجات، لتغليفها بشكل مرتب، وتوصيلها للعميل. كل اللي عليك كَبائع هو تركّز على شغلك وتدير متجرك على المنصة بكل سهولة!', null, 41];
        $rows[] = ['fulfillment', 'fbp_testimonial', 'author', 'text', 'Hekayat Sahab', 'حكايات سحاب', null, 42];

        // ─── page_key = 'smart-tools' (smart-tools.blade.php + partials) ───
        $rows[] = ['smart-tools', 'hero', 'title', 'text', 'Grow Smarter', 'نمِّ أعمالك بذكاء', null, 1];
        $rows[] = ['smart-tools', 'hero', 'subtitle', 'text', 'Ads, Fees and insights', 'الإعلانات، الرسوم، والرؤى التحليلية', null, 2];
        $rows[] = ['smart-tools', 'hero', 'tagline', 'text', 'Spend smarter, scale faster', 'أنفق بذكاء، وتوسّع بسرعة', null, 3];
        $rows[] = ['smart-tools', 'hero', 'photo', 'image', 'A noon warehouse in full motion', 'مستودع نون في حركة كاملة', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/04-hero.jpg', 4];

        $rows[] = ['smart-tools', 'ads', 'description_1', 'text', 'Ads that work as hard as you do. noon Ads is built for sellers serious about growth and expansion.', 'إعلانات تشتغل بجهدك نفسه. إعلانات نون مصممة للبائعين الجادين في النمو والتوسّع.', null, 10];
        $rows[] = ['smart-tools', 'ads', 'description_2', 'text', 'Reach more customers, grow your sales, and scale your business faster.', 'وصل لعدد أكبر من العملاء، زوّد مبيعاتك، ووسّع شغلك بشكل أسرع.', null, 11];
        $rows[] = ['smart-tools', 'ads', 'formats_label', 'text', 'With our multi-format solutions, you get access to:', 'مع حلولنا متعددة الصيغ، ستحصل على إمكانية الوصول إلى:', null, 12];
        $rows[] = ['smart-tools', 'ads', 'format_item_1', 'text', 'Product ads - to promote individual products and improve conversion', 'إعلانات المنتجات - للترويج للمنتجات الفردية وتحسين التحويل', null, 13];
        $rows[] = ['smart-tools', 'ads', 'format_item_2', 'text', 'Display ads - to promote campaigns like new launches, clearance, or seasonal offers', 'إعلانات العرض - للترويج للحملات مثل الإطلاق الجديد، التصفية أو العروض الموسمية', null, 14];
        $rows[] = ['smart-tools', 'ads', 'learn_more_button', 'link', 'Learn more', 'اعرف أكثر', '/advertise/sellers', 15];
        $rows[] = ['smart-tools', 'ads', 'photo', 'image', 'Your growth engine', 'محرك النمو الخاص بك', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-learn-more.png', 16];

        $rows[] = ['smart-tools', 'fee-structure', 'title', 'text', 'Fee structure', 'هيكل الرسوم', null, 20];
        $rows[] = ['smart-tools', 'fee-structure', 'subtitle', 'text', 'A transparent, simple fee structure with no hidden costs - built for sustainable growth.', 'هيكل رسوم شفاف وبسيط بدون أي تكاليف خفية – مصمم للنمو المستدام.', null, 21];
        $rows[] = ['smart-tools', 'fee-structure', 'photo', 'image', 'A man holding a box in the warehouse', 'رجل يحمل صندوقًا في المستودع', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/04-selling.jpg', 22];
        $rows[] = ['smart-tools', 'fee-structure', 'description', 'text', 'Your costs depend on your product category and fulfilment model.', 'تعتمد تكاليفك على فئة منتجك ونموذج التنفيذ.', null, 23];
        $rows[] = ['smart-tools', 'fee-structure', 'note', 'text', 'Fees are transparent, flexible, and designed to grow with you.', 'الإعدادات شفافة، مرنة، ومصممة لتنمو معك.', null, 24];
        $rows[] = ['smart-tools', 'fee-structure', 'learn_more_button', 'link', 'Learn more', 'اعرف أكثر', 'https://support.noon.partners/portal/en/kb/search/fees', 25];

        $rows[] = ['smart-tools', 'transparent-fees', 'title', 'text', 'Transparent Fees', 'الرسوم الشفافة', null, 30];

        $rows[] = ['smart-tools', 'fee-card-referral', 'title', 'text', 'Referral Fees', 'رسوم الاحالة', null, 31];
        $rows[] = ['smart-tools', 'fee-card-referral', 'tagline', 'text', 'We only earn when you do.', 'نحن نربح فقط عندما تحقق انت ربحا', null, 32];
        $rows[] = ['smart-tools', 'fee-card-referral', 'description', 'text', 'A referral fee applies per sale — based on your product category.', 'تطبق رسوم الاحالة علي كل عملية بيع - وفقا لفئة منتجك', null, 33];
        $rows[] = ['smart-tools', 'fee-card-referral', 'link_1', 'link', 'Learn more', 'اعرف أكثر', 'https://support.noon.partners/portal/en/kb/articles/referral-fees-in-noon-mena', 34];

        $rows[] = ['smart-tools', 'fee-card-fulfilment', 'title', 'text', 'Fulfilment Fees', 'رسوم التنفيذ', null, 35];
        $rows[] = ['smart-tools', 'fee-card-fulfilment', 'tagline', 'text', 'Your shipping, your call.', 'شحنك، خيارك', null, 36];
        $rows[] = ['smart-tools', 'fee-card-fulfilment', 'description', 'text', "You pay for what you use, no hidden charges - fee varies based on how you choose to fulfil.", 'تدفع مقابل ما تستخدمه فقط، بدون أي رسوم خفية – الرسوم تختلف حسب طريقة التنفيذ التي تختارها.', null, 37];
        $rows[] = ['smart-tools', 'fee-card-fulfilment', 'link_fbn', 'link', 'Learn About FBN Fees', 'تعرف على رسوم FBN', 'https://support.noon.partners/portal/en/kb/articles/fbn-fees', 38];
        $rows[] = ['smart-tools', 'fee-card-fulfilment', 'link_fbp', 'link', 'Learn About FBP Fees', 'تعرف على رسوم FBP', 'https://support.noon.partners/portal/en/kb/articles/fbp-fees', 39];

        $rows[] = ['smart-tools', 'fee-card-other', 'title', 'text', 'Other Costs', 'مصاريف اخري', null, 40];
        $rows[] = ['smart-tools', 'fee-card-other', 'tagline', 'text', 'Only if you opt in.', 'تطبق فقط اذا اخترت الاستفادة منها', null, 41];
        $rows[] = ['smart-tools', 'fee-card-other', 'description', 'text', 'Extra fees apply for things like long-term storage etc. — which are applied when you choose to avail these services.', 'تُضاف رسوم إضافية لأمور مثل التخزين طويل الأمد وغيرها – وتُطبق عند اختيارك استخدام هذه الخدمات.', null, 42];
        $rows[] = ['smart-tools', 'fee-card-other', 'link_1', 'link', 'Learn more', 'اعرف أكثر', 'https://advertise.noon.com/en/', 43];

        $rows[] = ['smart-tools', 'payouts', 'title_prefix', 'text', 'Receiving', 'استلام', null, 50];
        $rows[] = ['smart-tools', 'payouts', 'title_highlight', 'text', 'Payouts', 'الأرباح', null, 51];
        $rows[] = ['smart-tools', 'payouts', 'subtitle', 'text', 'All you have to do is link your bank account.', 'كل اللي عليك تربط حسابك البنكي.', null, 52];
        $rows[] = ['smart-tools', 'payouts', 'description', 'text', 'Your earnings land directly after fees are deducted - simple, fast, no hassle.', 'أرباحك توصل مباشرة بعد خصم الرسوم — بكل بساطة وسرعة، من غير تعقيد.', null, 53];
        $rows[] = ['smart-tools', 'payouts', 'cta_button', 'link', 'Discover how', 'اكتشف كيف', 'https://support.noon.partners/portal/en/kb/articles/how-do-i-receive-my-payouts', 54];

        $rows[] = ['smart-tools', 'insights', 'photo', 'image', 'A noon van in the street', 'شاحنة نون في الشارع', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/04-paid.jpg', 60];
        $rows[] = ['smart-tools', 'insights', 'title', 'text', 'Analytics & insights', 'التحليلات والرؤى', null, 61];
        $rows[] = ['smart-tools', 'insights', 'subtitle', 'text', 'Track your customer behaviour and sales performance, and improve your business with powerful analytics across:', 'تابع سلوك عملائك وأداء مبيعاتك وحسّن عملك باستخدام تحليلات قوية عبر:', null, 62];
        $rows[] = ['smart-tools', 'insights', 'item_1', 'text', 'Conversion funnel', 'مسار التحويل', null, 63];
        $rows[] = ['smart-tools', 'insights', 'item_2', 'text', 'Sales performance', 'أداء المبيعات', null, 64];
        $rows[] = ['smart-tools', 'insights', 'item_3', 'text', 'Financial reports', 'التقارير المالية', null, 65];
        $rows[] = ['smart-tools', 'insights', 'item_4', 'text', 'Account health monitoring', 'مراقبة صحة الحساب', null, 66];
        $rows[] = ['smart-tools', 'insights', 'item_5', 'text', 'Reputation management', 'إدارة السمعة', null, 67];
        $rows[] = ['smart-tools', 'insights', 'footer_note', 'text', 'Access a full suite of reports and dashboards any time through the Seller Hub.', 'يمكنك الوصول إلى مجموعة كاملة من التقارير ولوحات المعلومات في أي وقت عبر مركز البائعين.', null, 68];

        $rows[] = ['smart-tools', 'vantage', 'title', 'text', 'Vantage Analytics - your growth command centre', 'فانتج للتحليلات – مركز القيادة لنموك', null, 70];
        $rows[] = ['smart-tools', 'vantage', 'description', 'text',
            "Vantage is noon's advanced analytics and growth platform, built for sellers who want to scale smart. Think of it as your premium intelligence and analytics platform, with a set of free insights available to all sellers.",
            'فانتج هو منصة نون المتقدمة للتحليلات والنمو، مصممة للبائعين الذين يرغبون في التوسع بذكاء. تخيّله كمنصتك المميزة للذكاء والتحليلات، مع مجموعة من الرؤى المجانية المتاحة لجميع البائعين.', null, 71];

        // ─── page_key = 'home' (teaser blocks from partials/fulfillment.blade.php
        //     and partials/smart-tools.blade.php, both included on home.blade.php) ─
        $rows[] = ['home', 'fulfillment_teaser', 'photo', 'image', 'Shipping and Fulfilment', 'الشحن والتوصيل', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/03-fbn.jpg', 100];
        $rows[] = ['home', 'fulfillment_teaser', 'title', 'text', 'Shipping and Fulfilment', 'الشحن والتوصيل', null, 101];
        $rows[] = ['home', 'fulfillment_teaser', 'subtitle', 'text', 'Flexible fulfilment options that work for you', 'خيارات تنفيذ مرنة تناسبك', null, 102];
        $rows[] = ['home', 'fulfillment_teaser', 'description', 'text', 'Pick the model that fits your business today, and scale with confidence', 'اختر النموذج الذي يناسب عملك اليوم، وتوسع بثقة', null, 103];
        $rows[] = ['home', 'fulfillment_teaser', 'item_1', 'text', 'Fulfilled by noon — Built for speed', 'التنفيذ من قبل نون — مصمم للسرعة', null, 104];
        $rows[] = ['home', 'fulfillment_teaser', 'item_2', 'text', 'Fulfilled by Partner — Built for flexibility', 'التنفيذ من قبل الشريك — مصمم للمرونة', null, 105];
        $rows[] = ['home', 'fulfillment_teaser', 'learn_more_button', 'link', 'Learn more', 'اعرف أكثر', '/fulfillment', 106];

        $rows[] = ['home', 'smart_tools_teaser', 'title', 'text', 'Grow smarter', 'نمِّ أعمالك بذكاء', null, 110];
        $rows[] = ['home', 'smart_tools_teaser', 'subtitle', 'text', 'Everything you need to scale and stay ahead', 'كل ما تحتاجه للتوسع والبقاء في الصدارة', null, 111];
        $rows[] = ['home', 'smart_tools_teaser', 'learn_more_button', 'link', 'Learn more', 'اعرف أكثر', '/smart-tools', 112];
        $rows[] = ['home', 'smart_tools_teaser_item_1', 'title', 'text', 'Ads that deliver results', 'إعلانات تحقق نتائج', null, 113];
        $rows[] = ['home', 'smart_tools_teaser_item_1', 'description', 'text', 'Use noon Ads, our in-house advertising suite, to put your products in front of more customers.', 'استفد من إعلانات نون، مجموعتنا الإعلانية الداخلية لعرض منتجاتك أمام المزيد من العملاء.', null, 114];
        $rows[] = ['home', 'smart_tools_teaser_item_2', 'title', 'text', 'Know your costs', 'اعرف تكاليفك', null, 115];
        $rows[] = ['home', 'smart_tools_teaser_item_2', 'description', 'text', "With noon's competitive, transparent fee structure, you'll always know what you'll earn - no surprises, just growth", 'مع هيكل الرسوم التنافسي والشفاف من نون، ستعرف دائمًا ما ستكسبه - لا مفاجآت، فقط نمو', null, 116];
        $rows[] = ['home', 'smart_tools_teaser_item_3', 'title', 'text', 'Scale with insights', 'توسع مع الرؤى', null, 117];
        $rows[] = ['home', 'smart_tools_teaser_item_3', 'description', 'text', 'Turn data into smarter decisions with our powerful reporting and insights tools', 'حوّل البيانات إلى قرارات أذكى باستخدام أدوات التقارير والرؤى القوية لدينا', null, 118];

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

        PortalContent::flush('sellers');
        PortalContent::flush('how-it-works');
        PortalContent::flush('fulfillment');
        PortalContent::flush('smart-tools');
        PortalContent::flush('home');
    }
}
