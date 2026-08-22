<?php

namespace Database\Seeders;

use App\Models\PortalContent;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Portal Content" CMS — admin-editable bilingual text/links
 * rendered on resources/views/portal/*.blade.php via the portal_content()
 * and portal_link() helpers (see app/Helpers/portal_content.php).
 *
 * PATTERN (read this before adding more pages):
 *
 *   page_key   groups all rows for one logical page/partial family, e.g.
 *              'home', 'faq', 'nav', 'cta_footer', 'how-it-works'.
 *   block_key  groups the fields belonging to one visual block/component
 *              within that page, e.g. 'hero', 'faq_item_3', 'main'.
 *   field_key  a single field within that block, e.g. 'title', 'subtitle',
 *              'question', 'answer', 'label', 'cta_button'.
 *   type       'text' (short string), 'richtext' (longer HTML/markdown
 *              blob), or 'link' (value_en/value_ar hold the localized
 *              LABEL and value_url holds the href).
 *
 * To convert another blade file:
 *   1. Read the file, find every hardcoded `{{ $isAr ? '..' : '..' }}`
 *      ternary or literal string that should be admin-editable.
 *   2. Pick a page_key (usually the view name) and a block_key per visual
 *      section/component.
 *   3. Replace the ternary in the blade file with:
 *        portal_content('page_key', 'block_key', 'field_key', 'English fallback', 'Arabic fallback')
 *      or, for an <a> tag:
 *        @php($x = portal_link('page_key', 'block_key', 'field_key', 'English label', 'Arabic label', '/original/href'))
 *        <a href="{{ $x['url'] }}">{{ $x['label'] }}</a>
 *   4. Add a matching row below using the *original* hardcoded copy as the
 *      seed value, so admins start from exactly today's text.
 *
 * This pass covers: nav.blade.php, advertise-nav.blade.php,
 * partials/cta-footer.blade.php, partials/hero.blade.php (home hero),
 * partials/faq.blade.php (page_key='faq'). Remaining portal partials
 * (why-sell, how-it-works steps, fulfillment, smart-tools, testimonials,
 * help, advertise-*, smart-tools, fulfillment, sellers, register wizard)
 * can be converted later using the identical mechanical pattern above.
 */
class PortalContentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];

        // ─── page_key = 'nav' ──────────────────────────────────────────────
        $rows[] = ['nav', 'link_home', 'label', 'text', 'Home', 'الصفحة الرئيسية', null, 1];
        $rows[] = ['nav', 'link_how_it_works', 'label', 'text', 'Getting Started', 'البدء', null, 2];
        $rows[] = ['nav', 'link_fulfillment', 'label', 'text', 'Shipping & Fulfilment', 'الشحن والتوصيل', null, 3];
        $rows[] = ['nav', 'link_smart_tools', 'label', 'text', 'Grow Smarter', 'نمّي بذكاء', null, 4];
        $rows[] = ['nav', 'link_blog', 'label', 'text', 'Blog', 'المدونة', null, 5];
        $rows[] = ['nav', 'language_toggle', 'label', 'text', 'English', 'العربية', null, 6];

        // advertise-nav.blade.php labels (kept under page_key='nav' with a distinct block prefix)
        $rows[] = ['nav', 'advertise_nav', 'sellers', 'text', 'Sellers', 'البائعين', null, 10];
        $rows[] = ['nav', 'advertise_nav', 'brands', 'text', 'Brands', 'العلامات التجارية', null, 11];
        $rows[] = ['nav', 'advertise_nav', 'advertisers', 'text', 'Advertisers', 'المعلنين', null, 12];
        $rows[] = ['nav', 'advertise_nav', 'knowledge_hub', 'text', 'Knowledge Hub', 'مركز المعلومات', null, 13];
        $rows[] = ['nav', 'advertise_nav', 'start_now', 'text', 'Start now', 'ابدأ الآن', null, 14];
        $rows[] = ['nav', 'advertise_nav', 'contact_us', 'text', 'Contact us', 'اتصل بنا', null, 15];

        // ─── page_key = 'cta_footer' ───────────────────────────────────────
        $rows[] = ['cta_footer', 'main', 'eyebrow', 'text', 'Start now', 'ابدأ الآن', null, 1];
        $rows[] = ['cta_footer', 'main', 'title', 'text', 'Ready to sell?', 'جاهز للبيع؟', null, 2];
        $rows[] = ['cta_footer', 'main', 'subtitle', 'text', 'Join thousands of sellers across the region who are growing and thriving with noon.', 'انضم إلى آلاف البائعين في جميع أنحاء المنطقة الذين ينمون ويكبرون مع نون.', null, 3];
        $rows[] = ['cta_footer', 'main', 'button', 'link', 'Register now', 'سجل الآن', '/register', 4];
        $rows[] = ['cta_footer', 'main', 'copyright', 'text', 'noon. All rights reserved', 'نون. جميع الحقوق محفوظة', null, 5];

        // ─── page_key = 'home' ──────────────────────────────────────────────
        // NOTE: partials/hero.blade.php renders the headline as two separate
        // lines for its typewriter animation, so it reads title_line1/title_line2.
        $rows[] = ['home', 'hero', 'title_line1', 'text', 'Start selling on', 'ابدأ البيع على', null, 1];
        $rows[] = ['home', 'hero', 'title_line2', 'text', 'noon today!', 'نون اليوم!', null, 2];
        $rows[] = ['home', 'hero', 'cta_button', 'link', 'Register now', 'سجل الآن', '/register', 2];

        // ─── page_key = 'faq' ───────────────────────────────────────────────
        // NOTE: the FAQ question/answer items themselves live in the dedicated
        // Faq CRUD module (app/Models/Faq.php, seeded by FaqSeeder) — only the
        // static header/CTA copy around the accordion stays here.
        $rows[] = ['faq', 'header', 'eyebrow', 'text', 'FAQ', 'الأسئلة الشائعة', null, 1];
        $rows[] = ['faq', 'header', 'title', 'text', 'Questions Sellers Ask', 'أسئلة يسألها البائعون', null, 2];
        $rows[] = ['faq', 'header', 'subtitle', 'text', 'Answers to the most common questions about selling on Noon.', 'إجابات لأكثر الأسئلة شيوعاً حول البيع على نون.', null, 3];

        $rows[] = ['faq', 'contact_cta', 'title', 'text', 'Have Another Question?', 'لديك سؤال آخر؟', null, 100];
        $rows[] = ['faq', 'contact_cta', 'subtitle', 'text', 'Our dedicated support team is available 24/7 to answer all your inquiries.', 'فريق الدعم المخصص لدينا متاح ٢٤/٧ للإجابة على جميع استفساراتك.', null, 101];
        $rows[] = ['faq', 'contact_cta', 'button', 'link', 'Contact Support', 'تواصل مع الدعم', 'mailto:sellers@noon.com', 102];

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

        // PortalContent::flush('nav');
        // PortalContent::flush('cta_footer');
        // PortalContent::flush('home');
        $this->call([
            PortalContentSeederBatch1::class,
            PortalContentSeederBatch2::class,
            PortalContentSeederBatch3::class,
            PortalContentSeederBatch4::class,
        ]);
        PortalContent::flush('faq');
    }
}
