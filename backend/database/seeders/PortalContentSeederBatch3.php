<?php

namespace Database\Seeders;

use App\Models\PortalContent;
use Illuminate\Database\Seeder;

/**
 * Batch 3 of the "Portal Content" CMS seed — covers the register wizard
 * (register.blade.php, register-success.blade.php, register/step-1..5),
 * the home-only testimonials/help partials, and the account-setup partial
 * (used standalone on the how-it-works page). See PortalContentSeeder.php
 * for the full pattern documentation.
 */
class PortalContentSeederBatch3 extends Seeder
{
    public function run(): void
    {
        $rows = [];

        // ─── page_key = 'home' — partials/testimonials.blade.php ───────────
        $rows[] = ['home', 'testimonials', 'heading', 'text', 'Testimonials', 'شهادات', null, 1];
        $rows[] = ['home', 'testimonials', 'video_title', 'text', 'How PAN Emirates scaled their noon store', 'كيف نمت حول الإمارات متجرها على نون', null, 2];
        $rows[] = ['home', 'testimonials', 'eyebrow', 'text', 'Seller success story', 'قصة نجاح بائع', null, 3];
        $rows[] = ['home', 'testimonials', 'featured_title', 'text', 'PAN Home chose noon\'s Fulfilled by Partner (FBP) model — and became the #1 furniture seller in the region.', 'اختارت بان هوم نموذج التوصيل عن طريق البائع (FBP) من نون — وأصبحت البائع رقم 1 للأثاث في المنطقة.', null, 4];
        $rows[] = ['home', 'testimonials', 'featured_subtitle', 'text', 'Discover how dedicated support, tailored account management, and powerful tools helped drive their success.', 'اكتشف كيف ساعد الدعم المخصص، والإدارة المصممة خصيصا، والأدوات القوية في تحقيق نجاحهم.', null, 5];
        $rows[] = ['home', 'testimonials', 'featured_name', 'text', 'PAN Home', 'بان هوم', null, 6];

        $rows[] = ['home', 'testimonial_1', 'photo', 'image', 'Hamad Alblooshi, Founder', 'حمد البلوشي، المؤسس', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-testimonial-05.jpg', 10];
        $rows[] = ['home', 'testimonial_1', 'quote', 'richtext', '“noon Mahali reached out to us quickly and made the whole process easy. Since joining at the launch of Mahali, their support has been incredible. They helped us get our products live, boosted our sales, and focused heavily on promoting local brands. And let’s be honest—when people want to shop, they go to noon first.”', 'تواصل معنا فريق نون محلي بسرعة وسهّلوا علينا كل الإجراءات. من يوم انضمامنا مع إطلاق البرنامج، والدعم اللي قدموه لنا كان رائع. ساعدونا نفعّل منتجاتنا على المنصة، رفعوا مبيعاتنا، وكان تركيزهم كبير على دعم العلامات المحلية. وبصراحة، لما الناس تفكر تشتري، أول مكان يروحون له هو نون.', null, 11];
        $rows[] = ['home', 'testimonial_1', 'name', 'text', 'Hamad Alblooshi, Founder', 'حمد البلوشي، المؤسس', null, 12];
        $rows[] = ['home', 'testimonial_1', 'company', 'text', 'H2 Games', 'H2 Games', null, 13];

        $rows[] = ['home', 'testimonial_2', 'photo', 'image', 'Laila Alsaadi, Founder', 'ليلى السعدي، المؤسسة', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-testimonial-04.jpg', 20];
        $rows[] = ['home', 'testimonial_2', 'quote', 'richtext', '“I’ve always been passionate about e-commerce, which is why I chose to sell on noon and join Mahali. Getting paid for my first sale was an empowering moment that made me feel truly supported.”', 'لطالما كنت شغوفة بالتجارة الإلكترونية، ولهذا قررت البيع على نون والانضمام إلى برنامج مهلي. حصولي على أول دفعة بعد أول عملية بيع كان لحظة مليئة بالتمكين، وجعلتني أشعر بالدعم الحقيقي.', null, 21];
        $rows[] = ['home', 'testimonial_2', 'name', 'text', 'Laila Alsaadi, Founder', 'ليلى السعدي، المؤسسة', null, 22];
        $rows[] = ['home', 'testimonial_2', 'company', 'text', 'Heyraat General Trading', 'Heyraat General Trading', null, 23];

        $rows[] = ['home', 'testimonial_3', 'photo', 'image', 'Zouban Shalin, Brand Director', 'زوبان شالين، مدير العلامة التجارية', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-testimonial-02.jpg', 30];
        $rows[] = ['home', 'testimonial_3', 'quote', 'richtext', '“Partnering with noon helped Baybee grow fast and reach more customers across the UAE and KSA. Their support, tools, and reach made launching smooth and scaling easy. We’re excited to keep building on this success together.”', 'الشراكة مع نون ساعدت Baybee على النمو بسرعة والوصول إلى عدد أكبر من العملاء في الإمارات والسعودية. دعمهم، وأدواتهم، وانتشارهم الواسع جعلوا الإطلاق سلس والتوسع سهل. نحن متحمسون لمواصلة هذا النجاح معا.', null, 31];
        $rows[] = ['home', 'testimonial_3', 'name', 'text', 'Zouban Shalin, Brand Director', 'زوبان شالين، مدير العلامة التجارية', null, 32];
        $rows[] = ['home', 'testimonial_3', 'company', 'text', 'Baybee Brand', 'Baybee Brand', null, 33];

        $rows[] = ['home', 'testimonial_4', 'photo', 'image', 'Sreeja V.S, Head of e-Commerce', 'سريجا في. إس، رئيسة قسم التجارة الإلكترونية', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-testimonial-01.jpg', 40];
        $rows[] = ['home', 'testimonial_4', 'quote', 'richtext', '“Big thanks to the noon team for jumping in when our original account got unexpectedly blocked. They acted fast, got us back online, and had our back throughout. In just three months, our sales shot up by over 500% — that kind of growth only happens when you’ve got a team that truly shows up.”', 'نتوجه بجزيل الشكر لفريق نون على دعمهم الفوري عندما تم حظر حسابنا بشكل مفاجئ. تدخلوا بسرعة، أعادونا للعمل خلال وقت قياسي، وكانوا إلى جانبنا في كل خطوة. خلال ثلاثة أشهر فقط، ارتفعت مبيعاتنا بأكثر من 500٪ — هذا النوع من النمو لا يتحقق إلا بوجود فريق حقيقي يلتزم ويقف مع شركائه بكل احترافية.', null, 41];
        $rows[] = ['home', 'testimonial_4', 'name', 'text', 'Sreeja V.S, Head of e-Commerce', 'سريجا في. إس، رئيسة قسم التجارة الإلكترونية', null, 42];
        $rows[] = ['home', 'testimonial_4', 'company', 'text', 'SAMS Global LLC', 'SAMS Global LLC', null, 43];

        $rows[] = ['home', 'testimonial_5', 'photo', 'image', 'Hossain Al-Hasan, Founder', 'حسين الحسن، المؤسس', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-testimonial-03.jpg', 50];
        $rows[] = ['home', 'testimonial_5', 'quote', 'richtext', '“Partnering with noon has been a game-changer, driving an incredible 45% over-year growth. This wouldn’t have been possible without the unwavering support and dedication of noon team, who stood by me every step of the way.”', 'الشراكة مع نون كانت نقطة تحول، حيث ساهمت في تحقيق نمو مذهل بنسبة 45% مقارنة بالعام الماضي. هذا النجاح لم يكن ليتحقق لولا الدعم المستمر والتفاني من فريق نون، الذين ساندوني في كل خطوة على الطريق.', null, 51];
        $rows[] = ['home', 'testimonial_5', 'name', 'text', 'Hossain Al-Hasan, Founder', 'حسين الحسن، المؤسس', null, 52];
        $rows[] = ['home', 'testimonial_5', 'company', 'text', 'Golden Technology Trading', 'Golden Technology Trading', null, 53];

        // ─── page_key = 'home' — partials/help.blade.php ────────────────────
        $rows[] = ['home', 'help', 'title', 'text', 'Need Help?', 'هل تحتاج للمساعدة؟', null, 60];
        $rows[] = ['home', 'help', 'subtitle', 'text', "Grab our Seller's Guide or check out our video tutorials — everything you need, step-by-step.", 'حمّل دليل البائع أو شاهد فيديوهات الشرح — كل ما تحتاجه، خطوة بخطوة.', null, 61];
        $rows[] = ['home', 'help', 'guide_button', 'link', 'Download The Guide', 'حمل الدليل', '/faq', 62];
        $rows[] = ['home', 'help', 'tutorials_button', 'link', 'Watch The Tutorials', 'شاهد الفيديوهات', '/how-it-works', 63];

        // ─── page_key = 'account_setup' — partials/account-setup.blade.php ─
        $rows[] = ['account_setup', 'main', 'photo', 'image', 'Registration steps', 'خطوات التسجيل', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/04-selling.jpg', 1];
        $rows[] = ['account_setup', 'main', 'title', 'text', 'Set up your account', 'إعداد حسابك', null, 2];

        $rows[] = ['account_setup', 'step_1', 'title', 'text', 'Get your details ready', 'قم بتجهيز بياناتك', null, 10];
        $rows[] = ['account_setup', 'step_1', 'caption', 'text', 'BEFORE YOU START, HAVE THE FOLLOWING HANDY', 'قبل أن تبدأ، احرص على توفر ما يلي', null, 11];
        $rows[] = ['account_setup', 'step_1', 'item_1_text', 'text', 'Email address and a phone number', 'عنوان بريد إلكتروني ورقم هاتف', null, 12];
        $rows[] = ['account_setup', 'step_1', 'item_2_text', 'text', 'Commercial Registration / Trade License', 'السجل التجاري / رخصة التجارة', null, 13];
        $rows[] = ['account_setup', 'step_1', 'item_2_link', 'text', 'Not sure what this is? Learn more', 'لست متأكد ما هذا؟ تعرف علي المزيد', null, 14];
        $rows[] = ['account_setup', 'step_1', 'item_3_text', 'text', 'Identity proof — Passport / Emirates ID', 'إثبات الهوية — جواز السفر / بطاقة الهوية الإماراتية', null, 15];

        $rows[] = ['account_setup', 'step_2', 'title', 'text', 'Sign up as a seller', 'قم بالتسجيل كبائع', null, 20];
        $rows[] = ['account_setup', 'step_2', 'caption', 'text', 'CREATING YOUR SELLER ACCOUNT IS QUICK AND EASY', 'إنشاء حساب البائع الخاص بك سريع وسهل', null, 21];
        $rows[] = ['account_setup', 'step_2', 'item_1_text', 'text', 'Click on', 'اضغط على', null, 22];
        $rows[] = ['account_setup', 'step_2', 'item_1_inline_link', 'text', 'Sign up now', 'سجل الآن', null, 23];
        $rows[] = ['account_setup', 'step_2', 'item_2_text', 'text', 'Provide us with a few details', 'زودنا ببعض التفاصيل', null, 24];
        $rows[] = ['account_setup', 'step_2', 'item_3_text', 'text', 'Verify them', 'قم بالتحقق منها', null, 25];
        $rows[] = ['account_setup', 'step_2', 'after_list_text', 'text', 'And just like that, you are now part of the noon family', 'وهكذا، أصبحت الآن جزءاً من عائلة نون', null, 26];

        $rows[] = ['account_setup', 'step_3', 'title', 'text', 'Set up your store', 'إعداد متجرك', null, 30];
        $rows[] = ['account_setup', 'step_3', 'caption', 'text', 'CREATE YOUR STORE AND ADD YOUR BUSINESS DOCUMENTS', 'قم بإنشاء متجرك وإضافة مستندات عملك', null, 31];
        $rows[] = ['account_setup', 'step_3', 'paragraph_1', 'text', 'Create your store for the country that you wish to sell in and add your business documents for a quick and seamless approval', 'قم بإنشاء متجرك للبلد الذي ترغب في البيع فيه وأضف مستندات عملك للحصول على موافقة سريعة وسلسة', null, 32];
        $rows[] = ['account_setup', 'step_3', 'paragraph_2', 'text', 'Once you create your store, seller lab becomes your control center for everything noon', 'بمجرد إنشاء متجرك، يصبح مختبر البائع مركز التحكم الخاص بك لكل ما يتعلق بنون', null, 33];

        // ─── page_key = 'register' — register.blade.php ────────────────────
        $rows[] = ['register', 'meta', 'title', 'text', 'Register as a Seller — noon', 'سجّل كبائع — نون', null, 1];
        $rows[] = ['register', 'header', 'logo_tagline', 'text', 'for Sellers', 'للبائعين', null, 2];
        $rows[] = ['register', 'header', 'title', 'text', 'Join noon as a Seller', 'انضم إلى منصة نون كبائع', null, 3];
        $rows[] = ['register', 'header', 'subtitle', 'text', 'Complete the following steps to create your business account', 'أكمل الخطوات التالية لإنشاء حسابك التجاري', null, 4];
        $rows[] = ['register', 'progress', 'step_label', 'text', 'Step', 'الخطوة', null, 5];
        $rows[] = ['register', 'progress', 'of_label', 'text', 'of', 'من', null, 6];
        $rows[] = ['register', 'footer_nav', 'back_button', 'text', 'Back →', '← السابق', null, 7];
        $rows[] = ['register', 'footer_nav', 'next_button', 'text', '→ Next', 'التالي ←', null, 8];
        $rows[] = ['register', 'footer_nav', 'saving_label', 'text', 'Saving…', 'جارٍ الحفظ…', null, 9];
        $rows[] = ['register', 'footer_nav', 'submit_button', 'text', 'Submit Application ✓', 'إرسال الطلب ✓', null, 10];
        $rows[] = ['register', 'footer_nav', 'submitting_label', 'text', 'Submitting…', 'جارٍ الإرسال…', null, 11];
        $rows[] = ['register', 'login_prompt', 'text', 'text', 'Already have a seller account?', 'لديك حساب بائع بالفعل؟', null, 12];
        $rows[] = ['register', 'login_prompt', 'link', 'link', 'Log in', 'تسجيل الدخول', '/partner/login', 13];

        // ─── page_key = 'register' — register-success.blade.php ───────────
        $rows[] = ['register', 'success', 'page_title', 'text', 'Application Submitted — noon for Sellers', 'تم إرسال طلبك — نون للبائعين', null, 20];
        $rows[] = ['register', 'success', 'title', 'text', 'Thank you for registering!', 'شكراً لتسجيلك!', null, 21];
        $rows[] = ['register', 'success', 'intro_prefix', 'text', "We've received your application successfully. Our team will review your information and documents within", 'استلمنا طلبك بنجاح. سيقوم فريقنا بمراجعة بياناتك ووثائقك خلال', null, 22];
        $rows[] = ['register', 'success', 'intro_days', 'text', '3–5 business days', '٣–٥ أيام عمل', null, 23];
        $rows[] = ['register', 'success', 'intro_suffix', 'text', "and we'll reach out via email as soon as a decision is made.", 'وسنتواصل معك عبر البريد الإلكتروني فور اتخاذ القرار.', null, 24];
        $rows[] = ['register', 'success', 'next_steps_title', 'text', 'What happens next?', 'ماذا سيحدث بعد ذلك؟', null, 25];
        $rows[] = ['register', 'success', 'step_1_title', 'text', 'Application review', 'مراجعة الطلب', null, 26];
        $rows[] = ['register', 'success', 'step_1_text', 'text', 'Our team will review your information and documents within 3–5 business days', 'سيراجع فريقنا بياناتك ووثائقك خلال ٣–٥ أيام عمل', null, 27];
        $rows[] = ['register', 'success', 'step_2_title', 'text', 'Decision notification', 'إشعار القرار', null, 28];
        $rows[] = ['register', 'success', 'step_2_text', 'text', 'You will receive an email with the approval decision or a request for additional information', 'ستتلقى بريداً إلكترونياً يتضمن قرار القبول أو طلب معلومات إضافية', null, 29];
        $rows[] = ['register', 'success', 'step_3_title', 'text', 'Start selling!', 'ابدأ البيع!', null, 30];
        $rows[] = ['register', 'success', 'step_3_text', 'text', 'Once approved, you get full access to the seller dashboard to add your products', 'بعد الموافقة تحصل على وصول كامل للوحة تحكم البائع لإضافة منتجاتك', null, 31];
        $rows[] = ['register', 'success', 'home_button', 'link', 'Back to Home', 'العودة للرئيسية', '/', 32];
        $rows[] = ['register', 'success', 'support_button', 'link', 'Contact Support', 'تواصل مع الدعم', 'mailto:vendors@noon.com', 33];

        // ─── page_key = 'register' — register/step-1.blade.php ────────────
        $rows[] = ['register', 'step_1', 'heading', 'text', 'Account Information', 'معلومات الحساب', null, 100];
        $rows[] = ['register', 'step_1', 'name_label', 'text', 'Full Name', 'الاسم الكامل', null, 101];
        $rows[] = ['register', 'step_1', 'name_placeholder', 'text', 'John Smith', 'محمد العلي', null, 102];
        $rows[] = ['register', 'step_1', 'email_label', 'text', 'Business Email', 'البريد الإلكتروني التجاري', null, 103];
        $rows[] = ['register', 'step_1', 'phone_label', 'text', 'Phone Number', 'رقم الهاتف', null, 104];
        $rows[] = ['register', 'step_1', 'country_label', 'text', 'Country', 'الدولة', null, 105];
        $rows[] = ['register', 'step_1', 'country_placeholder', 'text', '— Select Country —', '— اختر الدولة —', null, 106];
        $rows[] = ['register', 'step_1', 'password_label', 'text', 'Password', 'كلمة المرور', null, 107];
        $rows[] = ['register', 'step_1', 'password_placeholder', 'text', 'At least 8 characters', '٨ أحرف على الأقل', null, 108];
        $rows[] = ['register', 'step_1', 'password_confirmation_label', 'text', 'Confirm Password', 'تأكيد كلمة المرور', null, 109];
        $rows[] = ['register', 'step_1', 'password_confirmation_placeholder', 'text', 'Re-enter your password', 'أعد إدخال كلمة المرور', null, 110];

        // ─── page_key = 'register' — register/step-2.blade.php ────────────
        $rows[] = ['register', 'step_2', 'heading', 'text', 'Store Information', 'معلومات المتجر', null, 120];
        $rows[] = ['register', 'step_2', 'store_name_label', 'text', 'Store Name', 'اسم المتجر', null, 121];
        $rows[] = ['register', 'step_2', 'store_name_placeholder', 'text', 'Elegance Store', 'متجر الأناقة', null, 122];
        $rows[] = ['register', 'step_2', 'store_slug_label', 'text', 'Store URL Slug', 'الرابط المختصر للمتجر', null, 123];
        $rows[] = ['register', 'step_2', 'store_slug_hint', 'text', '(used in your store URL)', '(يُستخدم في الرابط)', null, 124];
        $rows[] = ['register', 'step_2', 'store_slug_available', 'text', '✓ This URL is available', '✓ الرابط متاح', null, 125];
        $rows[] = ['register', 'step_2', 'store_slug_taken', 'text', '✗ This URL is already taken, try another', '✗ هذا الرابط مستخدم، جرب آخر', null, 126];
        $rows[] = ['register', 'step_2', 'store_description_label', 'text', 'Store Description', 'وصف المتجر', null, 127];
        $rows[] = ['register', 'step_2', 'optional_label', 'text', '(optional)', '(اختياري)', null, 128];
        $rows[] = ['register', 'step_2', 'store_description_placeholder', 'text', 'Briefly describe your store and products…', 'صف متجرك ومنتجاتك باختصار…', null, 129];
        $rows[] = ['register', 'step_2', 'business_type_label', 'text', 'Business Type', 'نوع النشاط التجاري', null, 130];
        $rows[] = ['register', 'step_2', 'business_type_individual', 'text', 'Individual', 'فرد / شخصي', null, 131];
        $rows[] = ['register', 'step_2', 'business_type_sole_prop', 'text', 'Sole Proprietorship', 'مؤسسة فردية', null, 132];
        $rows[] = ['register', 'step_2', 'business_type_llc', 'text', 'Limited Liability Company (LLC)', 'شركة ذات مسؤولية محدودة', null, 133];
        $rows[] = ['register', 'step_2', 'business_type_corp', 'text', 'Corporation', 'شركة مساهمة', null, 134];
        $rows[] = ['register', 'step_2', 'business_name_label', 'text', 'Official Business Name', 'الاسم التجاري الرسمي', null, 135];
        $rows[] = ['register', 'step_2', 'business_name_placeholder', 'text', 'Company name as officially registered', 'اسم الشركة كما هو مسجل رسمياً', null, 136];
        $rows[] = ['register', 'step_2', 'registration_number_label', 'text', 'Trade Registration Number', 'رقم السجل التجاري', null, 137];
        $rows[] = ['register', 'step_2', 'tax_id_label', 'text', 'Tax ID', 'الرقم الضريبي', null, 138];

        // ─── page_key = 'register' — register/step-3.blade.php ────────────
        $rows[] = ['register', 'step_3', 'heading', 'text', 'Contact & Address Information', 'بيانات التواصل والعنوان', null, 150];
        $rows[] = ['register', 'step_3', 'contact_email_label', 'text', 'Contact Email', 'البريد الإلكتروني للتواصل', null, 151];
        $rows[] = ['register', 'step_3', 'contact_phone_label', 'text', 'Contact Phone', 'هاتف التواصل', null, 152];
        $rows[] = ['register', 'step_3', 'whatsapp_label', 'text', 'WhatsApp', 'واتساب', null, 153];
        $rows[] = ['register', 'step_3', 'optional_label', 'text', '(optional)', '(اختياري)', null, 154];
        $rows[] = ['register', 'step_3', 'address_heading', 'text', 'Business Address', 'عنوان النشاط التجاري', null, 155];
        $rows[] = ['register', 'step_3', 'city_label', 'text', 'City', 'المدينة', null, 156];
        $rows[] = ['register', 'step_3', 'city_placeholder', 'text', '— Select City —', '— اختر المدينة —', null, 157];
        $rows[] = ['register', 'step_3', 'no_cities_notice', 'text', 'No cities are currently registered for this country.', 'لا توجد مدن مسجلة لهذه الدولة حالياً.', null, 158];
        $rows[] = ['register', 'step_3', 'area_label', 'text', 'District / Area', 'الحي / المنطقة', null, 159];
        $rows[] = ['register', 'step_3', 'area_placeholder', 'text', 'Al Rawda District', 'حي الروضة', null, 160];
        $rows[] = ['register', 'step_3', 'street_label', 'text', 'Street Name', 'اسم الشارع', null, 161];
        $rows[] = ['register', 'step_3', 'street_placeholder', 'text', 'King Fahd Street', 'شارع الملك فهد', null, 162];
        $rows[] = ['register', 'step_3', 'building_label', 'text', 'Building', 'المبنى / البناية', null, 163];
        $rows[] = ['register', 'step_3', 'building_placeholder', 'text', 'Business Tower', 'برج الأعمال', null, 164];
        $rows[] = ['register', 'step_3', 'floor_label', 'text', 'Floor', 'الطابق', null, 165];
        $rows[] = ['register', 'step_3', 'apartment_label', 'text', 'Office / Apartment Number', 'رقم المكتب / الشقة', null, 166];
        $rows[] = ['register', 'step_3', 'postal_code_label', 'text', 'Postal Code', 'الرمز البريدي', null, 167];

        // ─── page_key = 'register' — register/step-4.blade.php ────────────
        $rows[] = ['register', 'step_4', 'heading', 'text', 'Upload Official Documents', 'رفع الوثائق الرسمية', null, 180];
        $rows[] = ['register', 'step_4', 'subtitle', 'text', 'Accepted formats and maximum size vary by document type.', 'الصيغ المقبولة والحجم الأقصى يختلفان حسب نوع الوثيقة.', null, 181];
        $rows[] = ['register', 'step_4', 'loading_label', 'text', 'Loading document requirements…', 'جارٍ تحميل متطلبات الوثائق…', null, 182];
        $rows[] = ['register', 'step_4', 'no_docs_notice', 'text', 'No documents are required for this country — you may continue.', 'لا توجد وثائق مطلوبة لهذه الدولة، يمكنك المتابعة.', null, 183];
        $rows[] = ['register', 'step_4', 'optional_label', 'text', '(optional)', '(اختياري)', null, 184];
        $rows[] = ['register', 'step_4', 'accepted_types_label', 'text', 'Accepted types:', 'الأنواع المقبولة:', null, 185];
        $rows[] = ['register', 'step_4', 'max_size_label', 'text', 'Max size:', 'الحجم الأقصى:', null, 186];
        $rows[] = ['register', 'step_4', 'uploaded_label', 'text', 'Uploaded', 'تم الرفع', null, 187];
        $rows[] = ['register', 'step_4', 'choose_file_optional_label', 'text', 'Click to choose a file (optional)', 'انقر لاختيار الملف (اختياري)', null, 188];
        $rows[] = ['register', 'step_4', 'choose_file_label', 'text', 'Click to choose a file', 'انقر لاختيار الملف', null, 189];
        $rows[] = ['register', 'step_4', 'remove_label', 'text', 'Remove', 'حذف', null, 190];
        $rows[] = ['register', 'step_4', 'expiry_date_label', 'text', 'Expiry Date', 'تاريخ انتهاء الصلاحية', null, 191];
        $rows[] = ['register', 'step_4', 'optional_skip_note', 'text', '(Optional — can be added later from the dashboard)', '(اختياري — يمكن إضافتها لاحقاً من لوحة التحكم)', null, 192];
        $rows[] = ['register', 'step_4', 'uploading_label', 'text', 'Uploading…', 'جارٍ الرفع…', null, 193];

        // ─── page_key = 'register' — register/step-5.blade.php ────────────
        $rows[] = ['register', 'step_5', 'heading', 'text', 'Review & Confirm Your Application', 'مراجعة البيانات وتأكيد الطلب', null, 210];
        $rows[] = ['register', 'step_5', 'subtitle', 'text', 'Review your information before submitting. You can go back to make changes.', 'تحقق من بياناتك قبل الإرسال. يمكنك العودة للتعديل.', null, 211];
        $rows[] = ['register', 'step_5', 'account_summary_title', 'text', 'Account Information', 'معلومات الحساب', null, 212];
        $rows[] = ['register', 'step_5', 'name_label', 'text', 'Name', 'الاسم', null, 213];
        $rows[] = ['register', 'step_5', 'email_label', 'text', 'Email', 'البريد الإلكتروني', null, 214];
        $rows[] = ['register', 'step_5', 'phone_label', 'text', 'Phone Number', 'رقم الهاتف', null, 215];
        $rows[] = ['register', 'step_5', 'store_summary_title', 'text', 'Store Information', 'معلومات المتجر', null, 216];
        $rows[] = ['register', 'step_5', 'store_name_label', 'text', 'Store Name', 'اسم المتجر', null, 217];
        $rows[] = ['register', 'step_5', 'store_slug_label', 'text', 'Store Slug', 'الرابط المختصر', null, 218];
        $rows[] = ['register', 'step_5', 'business_type_label', 'text', 'Business Type', 'نوع النشاط', null, 219];
        $rows[] = ['register', 'step_5', 'business_name_label', 'text', 'Business Name', 'الاسم التجاري', null, 220];
        $rows[] = ['register', 'step_5', 'address_summary_title', 'text', 'Business Address', 'العنوان التجاري', null, 221];
        $rows[] = ['register', 'step_5', 'street_label', 'text', 'Street', 'الشارع', null, 222];
        $rows[] = ['register', 'step_5', 'city_label', 'text', 'City', 'المدينة', null, 223];
        $rows[] = ['register', 'step_5', 'documents_summary_title', 'text', 'Uploaded Documents', 'الوثائق المرفوعة', null, 224];
        $rows[] = ['register', 'step_5', 'required_label', 'text', '(required)', '(مطلوب)', null, 225];
        $rows[] = ['register', 'step_5', 'optional_label', 'text', '(optional)', '(اختياري)', null, 226];
        $rows[] = ['register', 'step_5', 'missing_docs_warning', 'text', 'Please go back to the previous step and upload the required documents before submitting.', 'يرجى العودة للخطوة السابقة ورفع الوثائق المطلوبة قبل الإرسال.', null, 227];
        $rows[] = ['register', 'step_5', 'agree_terms_heading', 'text', 'Agree to Terms', 'الموافقة على الشروط', null, 228];
        $rows[] = ['register', 'step_5', 'terms_prefix', 'text', "I agree to noon's", 'أوافق على', null, 229];
        $rows[] = ['register', 'step_5', 'terms_link', 'link', 'Terms and Conditions', 'الشروط والأحكام', '#', 230];
        $rows[] = ['register', 'step_5', 'terms_suffix', 'text', 'for sellers', 'الخاصة ببائعي نون', null, 231];
        $rows[] = ['register', 'step_5', 'privacy_prefix', 'text', 'I agree to the', 'أوافق على', null, 232];
        $rows[] = ['register', 'step_5', 'privacy_link', 'link', 'Privacy Policy', 'سياسة الخصوصية', '#', 233];
        $rows[] = ['register', 'step_5', 'privacy_suffix', 'text', 'and authorize noon to process my business data', 'وأذن لنون بمعالجة بياناتي التجارية', null, 234];

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

        PortalContent::flush('home');
        PortalContent::flush('account_setup');
        PortalContent::flush('register');
    }
}
