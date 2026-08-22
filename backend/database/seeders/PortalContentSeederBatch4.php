<?php

namespace Database\Seeders;

use App\Models\PortalContent;
use Illuminate\Database\Seeder;

/**
 * Batch 4 of the "Portal Content" CMS seed data — covers the static UI
 * chrome of the Blog, Help Center, and Ad Support portal sections
 * (resources/views/portal/blog/*, portal/helpcenter/*, portal/adsupport/*,
 * and their header/footer partials). See PortalContentSeeder.php for the
 * full pattern documentation.
 *
 * NOTE: blog posts, help articles, categories/collections, and their
 * per-record fields (title, body, excerpt, etc.) are dynamic, database
 * driven content and are intentionally NOT included here — only the
 * static surrounding chrome (headers, empty states, breadcrumb labels,
 * search placeholders, footer links, pagination/related labels) is
 * seeded.
 */
class PortalContentSeederBatch4 extends Seeder
{
    public function run(): void
    {
        $rows = [];

        // ─── page_key = 'blog' ──────────────────────────────────────────────
        $rows[] = ['blog', 'header', 'eyebrow', 'text', 'Noon Sellers Blog', 'مدونة نون للبائعين', null, 1];
        $rows[] = ['blog', 'header', 'subtitle', 'text', 'News, guides and tips from the Noon platform', 'أخبار وتوجيهات ونصائح من منصة نون', null, 2];
        $rows[] = ['blog', 'show', 'attachments_label', 'text', 'Attachments', 'المرفقات', null, 10];
        $rows[] = ['blog', 'show', 'views_label', 'text', 'views', 'مشاهدة', null, 11];

        // ─── page_key = 'helpcenter' ────────────────────────────────────────
        $rows[] = ['helpcenter', 'index', 'page_title', 'text', 'noon Seller Help Center', 'مركز مساعدة البائع', null, 1];
        $rows[] = ['helpcenter', 'index', 'page_description', 'text', 'noon Seller Help Center', 'مركز مساعدة البائع في نون', null, 2];
        $rows[] = ['helpcenter', 'index', 'article_word', 'text', 'article', 'مقالة', null, 3];
        $rows[] = ['helpcenter', 'index', 'no_categories', 'text', 'No categories yet.', 'لا توجد فئات بعد.', null, 4];
        $rows[] = ['helpcenter', 'index', 'learn_more', 'text', 'Learn more', 'اطّلع أكثر', null, 5];

        $rows[] = ['helpcenter', 'category', 'all_categories', 'text', 'All Categories', 'كل الفئات', null, 10];
        $rows[] = ['helpcenter', 'category', 'article_word', 'text', 'article', 'مقالة', null, 11];
        $rows[] = ['helpcenter', 'category', 'no_articles', 'text', 'Articles in this category are coming soon.', 'ستتوفر المقالات في هذه الفئة قريباً.', null, 12];

        $rows[] = ['helpcenter', 'article', 'all_categories', 'text', 'All Categories', 'كل الفئات', null, 20];
        $rows[] = ['helpcenter', 'article', 'views_label', 'text', 'views', 'مشاهدة', null, 21];
        $rows[] = ['helpcenter', 'article', 'table_of_contents', 'text', 'Table of contents', 'محتويات الصفحة', null, 22];
        $rows[] = ['helpcenter', 'article', 'related_articles', 'text', 'Related Articles', 'مقالات ذات صلة', null, 23];
        $rows[] = ['helpcenter', 'article', 'feedback_prompt', 'text', 'Did this answer your question?', 'هل أجاب هذا المقال على سؤالك؟', null, 24];

        $rows[] = ['helpcenter', 'search', 'results_word', 'text', 'Search results', 'نتائج البحث', null, 30];
        $rows[] = ['helpcenter', 'search', 'page_title', 'text', 'noon Seller Help Center', 'مركز مساعدة البائع', null, 31];
        $rows[] = ['helpcenter', 'search', 'results_for', 'text', 'Search results for', 'نتائج البحث عن', null, 32];
        $rows[] = ['helpcenter', 'search', 'no_results', 'text', 'No matching articles found.', 'لم يتم العثور على نتائج مطابقة.', null, 33];

        $rows[] = ['helpcenter', 'header', 'brand_label', 'text', 'Seller Help Center', 'مركز مساعدة البائع', null, 40];
        $rows[] = ['helpcenter', 'header', 'language_toggle_desktop', 'link', 'English', 'العربية', '/language/ar', 41];
        $rows[] = ['helpcenter', 'header', 'register_now', 'link', 'Register now', 'سجّل الآن', '/register', 42];
        $rows[] = ['helpcenter', 'header', 'contact_us', 'link', 'Contact us', 'تواصل معنا', 'mailto:seller@noon.com', 43];
        $rows[] = ['helpcenter', 'header', 'language_toggle_mobile', 'link', 'English', 'العربية', '/language/ar', 44];
        $rows[] = ['helpcenter', 'header', 'hero_title', 'text', 'Hi, how can we help you?', 'مرحباً، كيف يمكننا مساعدتك؟', null, 45];
        $rows[] = ['helpcenter', 'header', 'search_placeholder', 'text', 'Search for articles...', 'ابحث في المقالات...', null, 46];

        $rows[] = ['helpcenter', 'footer', 'brand_label', 'text', 'noon Seller Help Center', 'مركز مساعدة البائع', null, 50];
        $rows[] = ['helpcenter', 'footer', 'support_heading', 'text', 'Support', 'الدعم', null, 51];
        $rows[] = ['helpcenter', 'footer', 'contact_us', 'link', 'Contact us', 'تواصل معنا', 'mailto:seller@noon.com', 52];
        $rows[] = ['helpcenter', 'footer', 'related_links_heading', 'text', 'Related Links', 'روابط ذات صلة', null, 53];
        $rows[] = ['helpcenter', 'footer', 'our_website', 'link', 'Our website', 'موقعنا', '/', 54];
        $rows[] = ['helpcenter', 'footer', 'register_as_seller', 'link', 'Register as a seller', 'سجّل كبائع', '/register', 55];
        $rows[] = ['helpcenter', 'footer', 'copyright', 'text', 'All rights reserved.', 'جميع الحقوق محفوظة.', null, 56];

        // ─── page_key = 'adsupport' ─────────────────────────────────────────
        $rows[] = ['adsupport', 'index', 'page_title', 'text', 'Home | Advertise smarter, grow faster', 'الرئيسية | أعلن بذكاء، وانمُ أسرع', null, 1];
        $rows[] = ['adsupport', 'index', 'page_description', 'text', 'Advertise smarter, grow faster', 'أعلن بذكاء، وانمُ أسرع', null, 2];
        $rows[] = ['adsupport', 'index', 'article_word', 'text', 'article', 'مقالة', null, 3];
        $rows[] = ['adsupport', 'index', 'featured_heading', 'text', 'New to ads?', 'جديد على الإعلانات؟', null, 4];
        $rows[] = ['adsupport', 'index', 'learn_more', 'text', 'Learn more', 'اطّلع أكثر', null, 5];

        $rows[] = ['adsupport', 'collection', 'page_title_suffix', 'text', 'Advertise smarter, grow faster', 'أعلن بذكاء، وانمُ أسرع', null, 10];
        $rows[] = ['adsupport', 'collection', 'all_collections', 'text', 'All Collections', 'كل المجموعات', null, 11];
        $rows[] = ['adsupport', 'collection', 'article_word', 'text', 'article', 'مقالة', null, 12];
        $rows[] = ['adsupport', 'collection', 'no_articles', 'text', 'Articles in this collection are coming soon.', 'ستتوفر المقالات في هذه المجموعة قريباً.', null, 13];

        $rows[] = ['adsupport', 'article', 'page_title_suffix', 'text', 'Advertise smarter, grow faster', 'أعلن بذكاء، وانمُ أسرع', null, 20];
        $rows[] = ['adsupport', 'article', 'all_collections', 'text', 'All Collections', 'كل المجموعات', null, 21];
        $rows[] = ['adsupport', 'article', 'updated_label', 'text', 'Updated', 'تم التحديث', null, 22];
        $rows[] = ['adsupport', 'article', 'table_of_contents', 'text', 'Table of contents', 'محتويات الصفحة', null, 23];
        $rows[] = ['adsupport', 'article', 'related_articles', 'text', 'Related Articles', 'مقالات ذات صلة', null, 24];
        $rows[] = ['adsupport', 'article', 'feedback_prompt', 'text', 'Did this answer your question?', 'هل أجاب هذا المقال على سؤالك؟', null, 25];
        $rows[] = ['adsupport', 'article', 'reaction_disappointed', 'text', 'Disappointed Reaction', 'رد فعل مستاء', null, 26];
        $rows[] = ['adsupport', 'article', 'reaction_neutral', 'text', 'Neutral Reaction', 'رد فعل محايد', null, 27];
        $rows[] = ['adsupport', 'article', 'reaction_smiley', 'text', 'Smiley Reaction', 'رد فعل مبتسم', null, 28];

        $rows[] = ['adsupport', 'header', 'logo', 'image', 'Advertise smarter, grow faster', 'أعلن بذكاء، وانمُ أسرع', 'https://downloads.intercomcdn.com/i/o/yba8j1xj/654495/fd5bbb8bb174fa85e45ad370b70b/d9702cdb7e2403024b5c1d0d2a3f23e3.png', 30];
        $rows[] = ['adsupport', 'header', 'open_menu', 'text', 'Open menu', 'فتح القائمة', null, 31];
        $rows[] = ['adsupport', 'header', 'start_now', 'link', 'Start now', 'ابدأ الآن', '/register', 32];
        $rows[] = ['adsupport', 'header', 'contact_us', 'link', 'Contact us', 'تواصل معنا', '/advertise/request', 33];
        $rows[] = ['adsupport', 'header', 'close_menu', 'text', 'Close menu', 'إغلاق القائمة', null, 34];
        $rows[] = ['adsupport', 'header', 'start_now_mobile', 'link', 'Start now', 'ابدأ الآن', 'https://admanager.noon.partners/en-ae?utm_source=help_center&utm_medium=header', 35];
        $rows[] = ['adsupport', 'header', 'hero_title', 'text', 'Hi, how can we help you?', 'مرحباً، كيف يمكننا مساعدتك؟', null, 36];
        $rows[] = ['adsupport', 'header', 'search_placeholder', 'text', 'Search for articles...', 'ابحث في المقالات...', null, 37];

        $rows[] = ['adsupport', 'footer', 'brand_label', 'text', 'Advertise smarter, grow faster', 'أعلن بذكاء، وانمُ أسرع', null, 40];
        $rows[] = ['adsupport', 'footer', 'support_heading', 'text', 'Support', 'الدعم', null, 41];
        $rows[] = ['adsupport', 'footer', 'contact_us', 'link', 'Contact us', 'تواصل معنا', 'mailto:adsupport@noon.com', 42];
        $rows[] = ['adsupport', 'footer', 'webinars_heading', 'text', 'Webinars', 'الندوات عبر الإنترنت', null, 43];
        $rows[] = ['adsupport', 'footer', 'webinar_english', 'link', 'English', 'الإنجليزية', 'https://outlook.office365.com/book/NoonTrainingCalenderUAE@nooncorpio.onmicrosoft.com/s/4eJP6vrZXkGt97Ji8aCx2g2', 44];
        $rows[] = ['adsupport', 'footer', 'webinar_arabic', 'link', 'Arabic', 'العربية', 'https://outlook.office365.com/book/Noon_KSA_Training@nooncorpio.onmicrosoft.com/s/W4-kOEYWQEqUuin7sziZuw2', 45];
        $rows[] = ['adsupport', 'footer', 'related_links_heading', 'text', 'Related Links', 'روابط ذات صلة', null, 46];
        $rows[] = ['adsupport', 'footer', 'our_website', 'link', 'Our website', 'موقعنا', '/', 47];
        $rows[] = ['adsupport', 'footer', 'ad_manager', 'link', 'Ad Manager', 'مدير الإعلانات', '/register', 48];
        $rows[] = ['adsupport', 'footer', 'linkedin', 'link', 'Linkedin', 'Linkedin', 'https://www.linkedin.com/company/noon-ads/', 49];

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

        PortalContent::flush('blog');
        PortalContent::flush('helpcenter');
        PortalContent::flush('adsupport');
    }
}
