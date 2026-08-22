<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sortOrder = 0;

        foreach ($this->definitions() as $group => $sections) {
            foreach ($sections as $section => $keys) {
                foreach ($keys as $key => $def) {
                    $sortOrder += 10;

                    $row = [
                        'group' => $group,
                        'section' => $section,
                        'key' => $key,
                        'type' => $def['type'],
                        'value' => $def['default'] ?? null,
                        'options' => isset($def['options']) ? json_encode($def['options']) : null,
                        'default_value' => $def['default'] ?? null,
                        'allowed_extensions' => $def['allowed_extensions'] ?? null,
                        'max_size_kb' => $def['max_size_kb'] ?? null,
                        'is_public' => $def['is_public'] ?? false,
                        'sort_order' => $sortOrder,
                        'updated_at' => now(),
                    ];

                    \DB::table('content_settings')->updateOrInsert(
                        ['key' => $row['key']],
                        array_merge($row, [
                            'id' => (string) Str::uuid(),
                            'created_at' => now(),
                        ])
                    );
                }
            }
        }
    }

    /**
     * @return array<string, array<string, array<string, array<string, mixed>>>>
     */
    private function definitions(): array
    {
        return [
            'general' => [
                'platform' => [
                    'platform_name' => ['type' => 'text'],
                    'platform_tagline_en' => ['type' => 'text'],
                    'platform_tagline_ar' => ['type' => 'text'],
                    'platform_email' => ['type' => 'email'],
                    'platform_phone' => ['type' => 'phone'],
                    'platform_address_en' => ['type' => 'textarea'],
                    'platform_address_ar' => ['type' => 'textarea'],
                    'platform_logo_file' => ['type' => 'file', 'allowed_extensions' => 'jpg,jpeg,png,svg,webp', 'max_size_kb' => 500],
                    'platform_favicon_file' => ['type' => 'file', 'allowed_extensions' => 'ico,png', 'max_size_kb' => 100],
                    'platform_currency_note_en' => ['type' => 'text'],
                    'platform_currency_note_ar' => ['type' => 'text'],
                ],
                'contact' => [
                    'contact_whatsapp' => ['type' => 'phone'],
                    'contact_telegram' => ['type' => 'url'],
                    'contact_support_hours_en' => ['type' => 'text'],
                    'contact_support_hours_ar' => ['type' => 'text'],
                ],
            ],

            'appearance' => [
                'colors' => [
                    'color_primary' => ['type' => 'color', 'default' => '#FF6B00'],
                    'color_secondary' => ['type' => 'color', 'default' => '#1A1A2E'],
                    'color_accent' => ['type' => 'color', 'default' => '#FFD700'],
                ],
                'fonts' => [
                    'font_family_en' => ['type' => 'select', 'options' => ['Inter', 'Cairo', 'Poppins', 'Roboto']],
                    'font_family_ar' => ['type' => 'select', 'options' => ['Cairo', 'Tajawal', 'Almarai', 'Noto Kufi Arabic']],
                ],
            ],

            'homepage' => [
                'hero' => [
                    'home_hero_title_en' => ['type' => 'text'],
                    'home_hero_title_ar' => ['type' => 'text'],
                    'home_hero_subtitle_en' => ['type' => 'textarea'],
                    'home_hero_subtitle_ar' => ['type' => 'textarea'],
                    'home_hero_bg_image_file' => ['type' => 'file', 'allowed_extensions' => 'jpg,jpeg,png,webp', 'max_size_kb' => 2000],
                    'home_hero_cta_text_en' => ['type' => 'text'],
                    'home_hero_cta_text_ar' => ['type' => 'text'],
                    'home_hero_cta_url' => ['type' => 'url'],
                ],
                'announcement_bar' => [
                    'announcement_bar_enabled' => ['type' => 'boolean', 'default' => '0'],
                    'announcement_bar_text_en' => ['type' => 'text'],
                    'announcement_bar_text_ar' => ['type' => 'text'],
                    'announcement_bar_url' => ['type' => 'url'],
                    'announcement_bar_color' => ['type' => 'color'],
                ],
                'featured_section' => [
                    'featured_title_en' => ['type' => 'text'],
                    'featured_title_ar' => ['type' => 'text'],
                    'featured_subtitle_en' => ['type' => 'textarea'],
                    'featured_subtitle_ar' => ['type' => 'textarea'],
                ],
                'trust_badges' => [
                    'trust_badge_1_icon_file' => ['type' => 'file', 'allowed_extensions' => 'svg,png', 'max_size_kb' => 100],
                    'trust_badge_1_text_en' => ['type' => 'text'],
                    'trust_badge_1_text_ar' => ['type' => 'text'],
                    'trust_badge_2_icon_file' => ['type' => 'file', 'allowed_extensions' => 'svg,png', 'max_size_kb' => 100],
                    'trust_badge_2_text_en' => ['type' => 'text'],
                    'trust_badge_2_text_ar' => ['type' => 'text'],
                    'trust_badge_3_icon_file' => ['type' => 'file', 'allowed_extensions' => 'svg,png', 'max_size_kb' => 100],
                    'trust_badge_3_text_en' => ['type' => 'text'],
                    'trust_badge_3_text_ar' => ['type' => 'text'],
                ],
                'app_download' => [
                    'app_download_title_en' => ['type' => 'text'],
                    'app_download_title_ar' => ['type' => 'text'],
                    'app_download_subtitle_en' => ['type' => 'textarea'],
                    'app_download_subtitle_ar' => ['type' => 'textarea'],
                    'app_download_image_file' => ['type' => 'file', 'allowed_extensions' => 'jpg,jpeg,png,webp'],
                    'app_store_url' => ['type' => 'url'],
                    'play_store_url' => ['type' => 'url'],
                    'app_store_badge_file' => ['type' => 'file', 'allowed_extensions' => 'svg,png'],
                    'play_store_badge_file' => ['type' => 'file', 'allowed_extensions' => 'svg,png'],
                ],
            ],

            'footer' => [
                'main' => [
                    'footer_about_en' => ['type' => 'textarea'],
                    'footer_about_ar' => ['type' => 'textarea'],
                    'footer_copyright_en' => ['type' => 'text'],
                    'footer_copyright_ar' => ['type' => 'text'],
                    'footer_logo_file' => ['type' => 'file', 'allowed_extensions' => 'jpg,jpeg,png,svg,webp'],
                ],
                'social_links' => [
                    'social_facebook_url' => ['type' => 'url'],
                    'social_instagram_url' => ['type' => 'url'],
                    'social_twitter_url' => ['type' => 'url'],
                    'social_tiktok_url' => ['type' => 'url'],
                    'social_youtube_url' => ['type' => 'url'],
                    'social_linkedin_url' => ['type' => 'url'],
                    'social_snapchat_url' => ['type' => 'url'],
                ],
                'payments_badges' => [
                    'payment_badges_image_file' => ['type' => 'file', 'allowed_extensions' => 'jpg,jpeg,png,svg,webp'],
                    'payment_badges_note_en' => ['type' => 'text'],
                    'payment_badges_note_ar' => ['type' => 'text'],
                ],
            ],

            'auth_pages' => [
                'login' => [
                    'login_page_title_en' => ['type' => 'text'],
                    'login_page_title_ar' => ['type' => 'text'],
                    'login_page_subtitle_en' => ['type' => 'textarea'],
                    'login_page_subtitle_ar' => ['type' => 'textarea'],
                    'login_page_bg_image_file' => ['type' => 'file', 'allowed_extensions' => 'jpg,jpeg,png,webp'],
                ],
                'register' => [
                    'register_page_title_en' => ['type' => 'text'],
                    'register_page_title_ar' => ['type' => 'text'],
                    'register_welcome_text_en' => ['type' => 'editor'],
                    'register_welcome_text_ar' => ['type' => 'editor'],
                ],
            ],

            'vendor_portal' => [
                'landing' => [
                    'vendor_portal_hero_title_en' => ['type' => 'text'],
                    'vendor_portal_hero_title_ar' => ['type' => 'text'],
                    'vendor_portal_hero_subtitle_en' => ['type' => 'textarea'],
                    'vendor_portal_hero_subtitle_ar' => ['type' => 'textarea'],
                    'vendor_portal_hero_image_file' => ['type' => 'file', 'allowed_extensions' => 'jpg,jpeg,png,webp'],
                    'vendor_portal_cta_text_en' => ['type' => 'text'],
                    'vendor_portal_cta_text_ar' => ['type' => 'text'],
                ],
                'onboarding' => [
                    'vendor_onboarding_title_en' => ['type' => 'text'],
                    'vendor_onboarding_title_ar' => ['type' => 'text'],
                    'vendor_onboarding_body_en' => ['type' => 'editor'],
                    'vendor_onboarding_body_ar' => ['type' => 'editor'],
                    'vendor_onboarding_image_file' => ['type' => 'file', 'allowed_extensions' => 'jpg,jpeg,png,webp'],
                ],
                'support' => [
                    'vendor_support_email' => ['type' => 'email'],
                    'vendor_support_phone' => ['type' => 'phone'],
                    'vendor_support_hours_en' => ['type' => 'text'],
                    'vendor_support_hours_ar' => ['type' => 'text'],
                    'vendor_faq_url' => ['type' => 'url'],
                    'vendor_portal_logo_file' => ['type' => 'file', 'allowed_extensions' => 'jpg,jpeg,png,svg,webp'],
                ],
            ],

            'emails' => [
                'general' => [
                    'email_header_logo_file' => ['type' => 'file', 'allowed_extensions' => 'jpg,jpeg,png,svg,webp', 'max_size_kb' => 200],
                    'email_footer_text_en' => ['type' => 'textarea'],
                    'email_footer_text_ar' => ['type' => 'textarea'],
                    'email_primary_color' => ['type' => 'color'],
                    'email_from_name' => ['type' => 'text'],
                ],
                'order_emails' => [
                    'email_order_confirmed_intro_en' => ['type' => 'editor'],
                    'email_order_confirmed_intro_ar' => ['type' => 'editor'],
                    'email_order_shipped_intro_en' => ['type' => 'editor'],
                    'email_order_shipped_intro_ar' => ['type' => 'editor'],
                    'email_order_delivered_intro_en' => ['type' => 'editor'],
                    'email_order_delivered_intro_ar' => ['type' => 'editor'],
                ],
                'auth_emails' => [
                    'email_welcome_subject_en' => ['type' => 'text'],
                    'email_welcome_subject_ar' => ['type' => 'text'],
                    'email_welcome_body_en' => ['type' => 'editor'],
                    'email_welcome_body_ar' => ['type' => 'editor'],
                    'email_password_reset_subject_en' => ['type' => 'text'],
                    'email_password_reset_subject_ar' => ['type' => 'text'],
                    'email_password_reset_intro_en' => ['type' => 'editor'],
                    'email_password_reset_intro_ar' => ['type' => 'editor'],
                ],
            ],

            'seo' => [
                'defaults' => [
                    'seo_site_title_en' => ['type' => 'text'],
                    'seo_site_title_ar' => ['type' => 'text'],
                    'seo_meta_description_en' => ['type' => 'textarea'],
                    'seo_meta_description_ar' => ['type' => 'textarea'],
                    'seo_meta_keywords_en' => ['type' => 'text'],
                    'seo_meta_keywords_ar' => ['type' => 'text'],
                    'seo_og_image_file' => ['type' => 'file', 'allowed_extensions' => 'jpg,jpeg,png,webp', 'max_size_kb' => 1000],
                    'seo_twitter_card_type' => ['type' => 'select', 'options' => ['summary', 'summary_large_image']],
                    'google_analytics_id' => ['type' => 'text'],
                    'google_tag_manager_id' => ['type' => 'text'],
                    'facebook_pixel_id' => ['type' => 'text'],
                    'google_site_verification' => ['type' => 'text'],
                ],
            ],

            'policies' => [
                'documents' => [
                    'terms_of_service_en' => ['type' => 'editor', 'is_public' => true],
                    'terms_of_service_ar' => ['type' => 'editor', 'is_public' => true],
                    'privacy_policy_en' => ['type' => 'editor', 'is_public' => true],
                    'privacy_policy_ar' => ['type' => 'editor', 'is_public' => true],
                    'return_policy_en' => ['type' => 'editor', 'is_public' => true],
                    'return_policy_ar' => ['type' => 'editor', 'is_public' => true],
                    'cookie_policy_en' => ['type' => 'editor', 'is_public' => true],
                    'cookie_policy_ar' => ['type' => 'editor', 'is_public' => true],
                    'vendor_agreement_en' => ['type' => 'editor', 'is_public' => true],
                    'vendor_agreement_ar' => ['type' => 'editor', 'is_public' => true],
                    'shipping_policy_en' => ['type' => 'editor', 'is_public' => true],
                    'shipping_policy_ar' => ['type' => 'editor', 'is_public' => true],
                ],
            ],

            'notifications' => [
                'push' => [
                    'push_notif_order_title_en' => ['type' => 'text'],
                    'push_notif_order_title_ar' => ['type' => 'text'],
                    'push_notif_promo_title_en' => ['type' => 'text'],
                    'push_notif_promo_title_ar' => ['type' => 'text'],
                ],
            ],
        ];
    }
}
