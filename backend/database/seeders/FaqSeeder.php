<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Seeds the FAQ CRUD module (app/Models/Faq.php), managed in the admin
 * panel under Content > FAQs, and rendered on the public portal via
 * resources/views/portal/partials/{faq,product-faq,display-faq}.blade.php.
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedContext('seller', [
            ["What is Noon's commission on sales?", 'كم تبلغ عمولة نون على المبيعات؟', 'Noon\'s commission ranges from 5% to 15% depending on the category and product. You can use the commission calculator in the dashboard to accurately determine your specific product costs before listing.', 'تتراوح عمولة نون بين ٥٪ و١٥٪ حسب الفئة والمنتج. يمكنك استخدام حاسبة العمولات في لوحة التحكم لمعرفة تكاليف منتجاتك المحددة بدقة قبل النشر.'],
            ['When can I withdraw my earnings?', 'متى أستطيع سحب أرباحي؟', 'Seller payments are made periodically — weekly, bi-weekly, or monthly depending on sales volume and agreement. Funds are transferred directly to your verified registered bank account.', 'تتم مدفوعات البائعين دورياً — أسبوعياً أو نصف شهرياً أو شهرياً حسب حجم المبيعات والاتفاق. يتم التحويل مباشرة إلى حسابك البنكي المسجل بعد التحقق منه.'],
            ['Can I sell in more than one country?', 'هل يمكنني البيع في أكثر من دولة؟', 'Yes! Noon allows you to sell in UAE, Saudi Arabia, and Egypt from a single account. You can set prices and inventory for each market independently through the control panel.', 'نعم! تتيح لك نون البيع في الإمارات والمملكة العربية السعودية ومصر من حساب واحد. يمكنك ضبط الأسعار والمخزون لكل سوق بشكل مستقل من خلال لوحة التحكم.'],
            ['What documents are required for registration?', 'ما هي المستندات المطلوبة للتسجيل؟', 'You need: a business email, valid trade license, owner ID, and active bank account. Some categories may require additional certificates such as quality certification or tax registration.', 'تحتاج إلى: بريد إلكتروني تجاري، ترخيص تجاري ساري، هوية صاحب العمل، وحساب بنكي مفعّل. بعض الفئات قد تتطلب شهادات إضافية مثل شهادة الجودة أو التسجيل الضريبي.'],
            ['How long does the registration approval take?', 'كم يستغرق قبول طلب التسجيل؟', 'Registration review typically takes 2 to 5 business days after submitting all required documents. You will receive an email notification as soon as a decision is made on your application.', 'عادةً تستغرق مراجعة الطلب من ٢ إلى ٥ أيام عمل بعد تقديم جميع المستندات المطلوبة. ستتلقى إشعاراً بالبريد الإلكتروني فور البت في طلبك.'],
            ['Are there monthly subscription fees?', 'هل هناك رسوم اشتراك شهرية؟', 'Registration is completely free. Noon only takes a commission on each actual sale. There are no monthly or annual subscription fees.', 'التسجيل مجاني تماماً. نون تأخذ فقط عمولة على كل عملية بيع تتم فعلياً. لا توجد رسوم اشتراك شهرية أو سنوية.'],
            ['How do I handle returns?', 'كيف أتعامل مع المرتجعات؟', 'In the FBN model, Noon handles returns completely. In the SFS model, you receive a notification about the returned order and coordinate according to the return policy set in your store.', 'في نموذج FBN، تتولى نون إدارة المرتجعات بالكامل. في نموذج SFS، تتلقى إشعاراً بالطلب المُرجع وتقوم بالتنسيق وفق سياسة الإرجاع المحددة في متجرك.'],
            ['Can I update my product prices at any time?', 'هل يمكنني تعديل أسعار منتجاتي في أي وقت؟', 'Yes, you can update prices and inventory at any time through the seller dashboard. Changes reflect on the platform within minutes.', 'نعم، يمكنك تحديث الأسعار والمخزون في أي وقت من خلال لوحة تحكم البائع. التغييرات تنعكس على المنصة في غضون دقائق.'],
        ]);

        $this->seedContext('product_ads', [
            ['What are Product Ads?', 'ما هي إعلانات المنتجات؟', 'Product Ads are self-serve ads that are based on customer intent and appear when users search for specific products or product categories on noon.com. They work on a keyword/category bidding feature where advertisers bid on keywords and categories and their ads are displayed if they win the bid.', 'تعد إعلانات المنتجات جزءًا من مجموعتنا الإعلانية ذاتية الخدمة. وهي تعتمد على نية العميل في العثور على المنتجات ذات الصلة التي تظهر عادةً عندما يبحث المستخدمون عن منتجات أو فئات منتجات معينة على نون. تعمل على ميزة عروض أسعار الكلمات الرئيسية/الفئة حيث يقوم المعلنون بتقديم عروض أسعار على الكلمات الرئيسية والفئات ويتم عرض إعلاناتهم في حالة فوزهم بالعرض.'],
            ['Who can run Product Ads campaigns?', 'من يمكنه تشغيل حملات إعلانات المنتجات؟', 'Any partner, including a brand, distributor or seller who is registered on our noon Partners platform will have access to run these ads.', 'يمكن لأي شريك، بما في ذلك العلامة التجارية أو الموزع أو البائع المسجل على منصة شركاء نون الخاصة بنا، من الوصول لعرض هذه الإعلانات.'],
            ['How can I get started with Product Ads?', 'كيف يمكنني البدء في استخدام إعلانات المنتجات؟', 'Click on the "Start Now" button at the top right of the page and register as a noon Partner to have access to our ads manager. If you are already a noon Partner, login and you will be automatically directed to the ads manager where you can select your campaign type as "Product Ads".', "إذا كان لديك بالفعل حق الوصول إلى لوحة تحكم شركاء نون، فما عليك سوى الضغط على 'إنشاء حملة' واختيار إعلانات المنتج كنوع إعلانك وملء الحقول المطلوبة."],
            ['What is the pricing model for Product Ads and how much do they cost?', 'ما هو نموذج التسعير لإعلانات المنتجات وما هي تكلفتها؟', 'Product Ads use a CPC (cost per click) model where when a customer clicks on your ad, you will be charged the bid amount that is set for the targets in your campaign.', 'يمكنك تقديم عرض سعر لكل كلمة رئيسية و/أو فئة/فئة فرعية تختار استهدافها عبر حملة إعلانات المنتجات الخاصة بك. إذا كنت تقوم بإعداد حملتك الأولى لإعلانات المنتجات، فاستفد من عرض السعر المقترح الموضح ضمن الكلمات الرئيسية/الفئات المحددة.'],
            ['Can I edit a live campaign?', 'هل يمكنني تعديل حملة مباشرة؟', 'Yes, live campaigns can be edited directly through the ads manager. Scroll to the campaigns list and select the one you want to edit, the edit option should appear. Do not forget to click on "Save and Launch" to get the campaign running again.', "نعم، يمكنك تعديل حملة مباشرة. في لوحة تحكم شركاء نون، قم بالتمرير للأسفل إلى قائمة حملاتك. حدد الحملة التي تريد تعديلها واضغط على 'تعديل'. بمجرد الرضا، اضغط فوق 'حفظ وإطلاق' لتنشيط الحملة مرة أخرى."],
            ['Where will Product Ads be visible?', 'أين ستكون إعلانات المنتجات مرئية؟', 'Product Ads will be visible in the noon app and on web in between search results for the product listing pages and product description pages.', 'تظهر إعلانات المنتجات بين نتائج البحث في صفحات قائمة المنتجات وفي صفحات وصف المنتج.'],
            ['What type of performance reporting is available?', 'ما هو نوع تقارير الأداء المتاحة؟', 'Our self-service dashboard puts all major metrics at your fingertips, allowing you to monitor performance in real-time. Dive deeper with granular data extracts. Download and analyze data in your preferred tool for advanced insights and campaign optimization.', 'تضع لوحة التحكم ذاتية الخدمة جميع المقاييس الرئيسية في متناول يدك، مما يسمح لك بمراقبة الأداء في الوقت الفعلي. تعمق أكثر مع مقتطفات البيانات الدقيقة. قم بتنزيل البيانات وتحليلها في أداتك المفضلة للحصول على رؤى متقدمة وتحسين الحملة.'],
            ['How can I get access to the dashboard?', 'كيف يمكنني الوصول إلى لوحة التحكّم؟', 'Anyone can access the noon Partner platform and register for a self-serve dashboard.', 'يمكن لأي شخص الوصول إلى منصة شريك نون والتسجيل في لوحة تحكم ذاتية الخدمة.'],
        ]);

        $this->seedContext('display_ads', [
            ['What are Display Ads?', 'ما هي إعلانات العرض؟', 'Display Ads are static graphic banners displayed on the noon website and app.', 'إعلانات العرض هي بانرز بتصميم جرافيك ثابتة يتم عرضها على موقع وتطبيق نون.'],
            ['What is the pricing model and how much do Display Ads cost?', 'ما هو نموذج التسعير وكم تكلفته؟', 'Display ads follow a CPM model (Cost-per-mille or Cost-per-thousand) based bidding. You bid for every 1000 views. The ad campaign with the winning bid will be the one displayed on noon.', 'تتبع إعلانات العرض نموذج عروض الأسعار المستند إلى التكلفة لكل ألف ظهور (CPM). أنت تقدم عرضًا مقابل كل 1000 مشاهدة. ستكون الحملة الإعلانية ذات العرض الفائز هي الحملة التي سيتم عرضها على نون.'],
            ["How are Display Ads different from managed Display Ads? How much does noon's managed ads cost?", 'كيف تختلف إعلانات العرض عن إعلانات العرض المُدارة؟ ما هي تكلفة الإعلانات المُدارة من نون؟', "noon's Display Ads are self managed by advertisers whereas managed Display Ads are handled by the noon ads team. Managed Display Ads require a minimum spend of $1,500 USD.", 'عادةً ما تُدار الإعلانات المُدارة لدى نون من قِبل فريق إعلانات نون بدلًا من المعلن نفسه، وتتطلب حدًا أدنى للإنفاق قدره 1,500 دولار أمريكي.'],
            ['Who can run Display Ads campaigns?', 'من يمكنه تشغيل حملات إعلانات العرض ذاتية الخدمة؟', 'Display Ads are open to all sellers, distributors and brands that are active on noon. You will be given access to the self-serve dashboard to create, edit and track your campaigns.', 'إعلانات العرض متاحة لجميع البائعين والموزعين والعلامات التجارية النشطة على نون. سيتم منحك حق الوصول إلى لوحة التحكم ذاتية الخدمة لإنشاء حملاتك وتعديلها وتتبعها.'],
            ['How can I get started with Display Ads?', 'كيف يمكنني البدء في استخدام إعلانات العرض؟', 'Click on the "Start Now" button at the top right of the page and register as a noon Partner to have access to our ads manager. If you are already a noon Partner, login and you will be automatically directed to the ads manager where you can select your campaign type as "Display Ads".', "إذا كان لديك بالفعل حق الوصول إلى لوحة تحكم شركاء نون، فما عليك سوى الضغط على 'إنشاء حملة' واختيار إعلانات العرض كنوع إعلانك وملء الحقول المطلوبة."],
            ['Can I edit a live campaign?', 'هل يمكنني تعديل حملة مباشرة؟', 'Yes, live campaigns can be edited directly through the ads manager. Scroll to the campaigns list and select the one you want to edit, the edit option should appear. Do not forget to click on "Save and Launch" to get the campaign running again.', "نعم، يمكنك تعديل حملة مباشرة. في لوحة تحكم شركاء نون، قم بالتمرير للأسفل إلى قائمة حملاتك. حدد الحملة التي تريد تعديلها واضغط على 'تعديل'. بمجرد الرضا، اضغط فوق 'حفظ وإطلاق' لتنشيط الحملة مرة أخرى."],
            ['Where will my ads be visible?', 'أين ستكون إعلاناتي مرئية؟', "Display Ads are visible as strip banners on noon's Homepage and Category Pages.", 'تظهر إعلانات العرض على شكل لافتات شريطية على الصفحة الرئيسية وصفحات الفئات الخاصة بنون.'],
            ['How can I get access to the dashboard?', 'كيف يمكنني الوصول إلى لوحة التحكّم؟', 'Anyone can access the noon Partner platform and register for a self-serve dashboard.', 'يمكن لأي شخص الوصول إلى منصة شريك نون والتسجيل في لوحة تحكم ذاتية الخدمة.'],
            ['What type of performance reporting is available?', 'ما هو نوع تقارير الأداء المتاحة؟', 'Our self-service dashboard puts all major metrics at your fingertips, allowing you to monitor performance in real-time. Dive deeper with granular data extracts. Download and analyze data in your preferred tool for advanced insights and campaign optimization.', 'تضع لوحة التحكم ذاتية الخدمة جميع المقاييس الرئيسية في متناول يدك، مما يسمح لك بمراقبة الأداء في الوقت الفعلي. تعمق أكثر مع مقتطفات البيانات الدقيقة. قم بتنزيل البيانات وتحليلها في أداتك المفضلة للحصول على رؤى متقدمة وتحسين الحملة.'],
        ]);
    }

    /**
     * @param array<int, array{0:string,1:string,2:string,3:string}> $items [q_en, q_ar, a_en, a_ar]
     */
    private function seedContext(string $context, array $items): void
    {
        if (Faq::where('context', $context)->exists()) {
            return;
        }

        foreach ($items as $i => [$qEn, $qAr, $aEn, $aAr]) {
            Faq::create([
                'context' => $context,
                'question_en' => $qEn,
                'question_ar' => $qAr,
                'answer_en' => $aEn,
                'answer_ar' => $aAr,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }
    }
}
