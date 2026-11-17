<?php

use App\Models\Setting;
use App\Models\StaticPage;
use Database\Seeders\StaticPageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /** Placeholder values the original settings seeder shipped with. */
    private const SEED_PLACEHOLDERS = [
        'support_email' => 'support@platform.com',
        'support_phone' => '+20 2 12345678',
    ];

    /** Pages that get the real content (only while they still hold the seeded placeholder text). */
    private const SLUGS = ['contact-us', 'terms-and-conditions', 'privacy-policy', 'return-policy', 'refund-policy', 'shipping-policy'];

    private const PLACEHOLDER_MARKER = 'This is placeholder content.';

    /**
     * Admin → Settings → Business Info (category "business") plus the real Contact / policy page content,
     * which pulls those details through {{business_*}} placeholders (App\Support\BusinessInfo).
     *
     * Live-data safe: settings are only created when missing; existing support_email / support_phone are
     * moved to the new tab and only cleared if they still hold the seeded dummy value; a page is only
     * rewritten while its content still contains the seeded placeholder text, so admin edits are kept.
     */
    public function up(): void
    {
        $settings = [
            ['business_trade_name', 'Nawy', 'Brand / trade name shown to customers'],
            ['business_legal_name', '', 'Legal company name exactly as on the Commercial Registration'],
            ['business_cr_number', '', 'Commercial Registration (CR) number'],
            ['business_vat_number', '', 'VAT registration number (leave empty if not registered)'],
            ['business_address_en', '', 'Registered business address (English)'],
            ['business_address_ar', '', 'Registered business address (Arabic)'],
            ['support_email', '', 'Customer-facing support email'],
            ['support_phone', '', 'Customer-facing support phone, with country code (e.g. +968 9123 4567)'],
            ['business_whatsapp', '', 'Customer support WhatsApp number, with country code'],
            ['business_support_hours_en', '', 'Support working hours (English), e.g. Sunday–Thursday, 9:00 AM – 6:00 PM (Oman time)'],
            ['business_support_hours_ar', '', 'Support working hours (Arabic)'],
        ];

        foreach ($settings as [$key, $value, $description]) {
            $setting = Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'category' => 'business', 'description' => $description, 'is_encrypted' => 0, 'is_public' => 1]
            );

            $attributes = ['category' => 'business', 'description' => $description];
            if (array_key_exists($key, self::SEED_PLACEHOLDERS) && $setting->value === self::SEED_PLACEHOLDERS[$key]) {
                $attributes['value'] = '';
            }
            $setting->update($attributes);

            Cache::forget('setting:'.$key);
        }

        foreach (['general', 'business', 'public'] as $group) {
            Cache::forget('settings:'.$group);
        }

        $pages = StaticPage::whereNull('country_id')->whereIn('slug', self::SLUGS)->get();

        foreach ($pages as $page) {
            if (! str_contains((string) $page->content_en, self::PLACEHOLDER_MARKER)) {
                continue;
            }

            $attributes = [
                'content_en' => StaticPageSeeder::content($page->slug, 'en'),
                'content_ar' => StaticPageSeeder::content($page->slug, 'ar'),
            ];

            if ($page->slug === 'shipping-policy' && $page->title_en === 'Shipping Policy') {
                $attributes += [
                    'title_en' => 'Shipping & Delivery Policy',
                    'title_ar' => 'سياسة الشحن والتوصيل',
                    'seo_title_en' => 'Shipping & Delivery Policy | Nawy',
                    'seo_title_ar' => 'سياسة الشحن والتوصيل | ناوي',
                ];
            }

            $page->update(array_filter($attributes, fn ($value) => $value !== null));
        }
    }

    /** Content and settings are live data an admin may have edited since; nothing is reverted. */
    public function down(): void {}
};
