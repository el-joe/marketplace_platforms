<?php

namespace Database\Seeders;

use App\Models\StaticPage;
use Illuminate\Database\Seeder;

/**
 * Starter content pages for the Static Pages module (app/Models/StaticPage.php).
 *
 * Idempotent and safe on live data: keyed by slug, a page is only created when
 * no row (including a soft-deleted one) has that slug, so re-running never
 * overwrites admin edits or resurrects a page an admin deleted.
 *
 * Run: php artisan db:seed --class=StaticPageSeeder
 */
class StaticPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $i => $page) {
            if (StaticPage::withTrashed()->whereNull('country_id')->where('slug', $page['slug'])->exists()) {
                continue;
            }

            StaticPage::create(array_merge([
                'audience' => StaticPage::AUDIENCE_BOTH,
                'show_in_footer' => true,
                'sort_order' => $i + 1,
                'is_active' => true,
            ], $page));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            $this->page('about-us', StaticPage::FOOTER_GROUP_COMPANY, 'About Us', 'من نحن',
                'Learn more about Nawy, our mission and the team behind the marketplace.',
                'تعرّف على ناوي ورسالتنا والفريق الذي يقف وراء المنصة.'),
            $this->page('contact-us', StaticPage::FOOTER_GROUP_HELP, 'Contact Us', 'تواصل معنا',
                'How to reach the Nawy customer support team by email, phone or WhatsApp.',
                'طرق التواصل مع فريق خدمة عملاء ناوي عبر البريد الإلكتروني أو الهاتف أو واتساب.'),
            $this->page('terms-and-conditions', StaticPage::FOOTER_GROUP_POLICIES, 'Terms & Conditions', 'الشروط والأحكام',
                'The terms and conditions that govern your use of the Nawy platform.',
                'الشروط والأحكام التي تنظّم استخدامك لمنصة ناوي.'),
            $this->page('privacy-policy', StaticPage::FOOTER_GROUP_POLICIES, 'Privacy Policy', 'سياسة الخصوصية',
                'How Nawy collects, uses and protects your personal data.',
                'كيف تجمع ناوي بياناتك الشخصية وتستخدمها وتحميها.'),
            $this->page('return-policy', StaticPage::FOOTER_GROUP_POLICIES, 'Return Policy', 'سياسة الإرجاع',
                'Eligibility, timelines and steps for returning an order on Nawy.',
                'شروط الأهلية والمدد الزمنية وخطوات إرجاع الطلبات على ناوي.'),
            $this->page('refund-policy', StaticPage::FOOTER_GROUP_POLICIES, 'Refund Policy', 'سياسة الاسترداد',
                'When and how refunds are issued for cancelled or returned orders, including card payments.',
                'متى وكيف يتم استرداد المبالغ للطلبات الملغاة أو المرتجعة، بما في ذلك المدفوعات بالبطاقة.'),
            $this->page('shipping-policy', StaticPage::FOOTER_GROUP_POLICIES, 'Shipping Policy', 'سياسة الشحن',
                'Delivery areas, timelines and shipping fees on Nawy.',
                'مناطق التوصيل والمدد الزمنية ورسوم الشحن على ناوي.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function page(string $slug, string $group, string $titleEn, string $titleAr, string $summaryEn, string $summaryAr): array
    {
        return [
            'slug' => $slug,
            'footer_group' => $group,
            'title_en' => $titleEn,
            'title_ar' => $titleAr,
            'content_en' => "<h2>{$titleEn}</h2>\n<p>{$summaryEn}</p>\n<p>This is placeholder content. Please update it from the admin panel.</p>",
            'content_ar' => "<h2>{$titleAr}</h2>\n<p>{$summaryAr}</p>\n<p>هذا محتوى مؤقت. يرجى تحديثه من لوحة التحكم.</p>",
            'seo_title_en' => "{$titleEn} | Nawy",
            'seo_title_ar' => "{$titleAr} | ناوي",
            'seo_description_en' => $summaryEn,
            'seo_description_ar' => $summaryAr,
        ];
    }
}
