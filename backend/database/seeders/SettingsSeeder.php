<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [

            // ─── general ──────────────────────────────────────────────────────
            ['key' => 'site_name', 'value' => 'Nawy', 'category' => 'general', 'description' => 'Platform display name', 'is_public' => 1],
            ['key' => 'site_tagline', 'value' => 'Anything you need, Nawy delivers', 'category' => 'general', 'description' => 'Short tagline shown on homepage', 'is_public' => 1],
            ['key' => 'support_email', 'value' => 'support@platform.com', 'category' => 'general', 'description' => 'Customer-facing support email', 'is_public' => 1],
            ['key' => 'support_phone', 'value' => '+20 2 12345678', 'category' => 'general', 'description' => 'Customer-facing support phone', 'is_public' => 1],
            ['key' => 'default_locale', 'value' => 'ar', 'category' => 'general', 'description' => 'Default language (en or ar)', 'is_public' => 1],
            ['key' => 'join_option_product_vendor_enabled', 'value' => true, 'category' => 'general', 'description' => 'Show "Product Seller" on the /join page', 'is_public' => 0],
            ['key' => 'join_option_open_souq_enabled', 'value' => true, 'category' => 'general', 'description' => 'Show "Open Souq" (classifieds) on the /join page', 'is_public' => 0],
            ['key' => 'join_option_travel_agency_enabled', 'value' => true, 'category' => 'general', 'description' => 'Show "Travel Agency" on the /join page', 'is_public' => 0],
            ['key' => 'join_option_marketer_enabled', 'value' => true, 'category' => 'general', 'description' => 'Show "Influencer / Marketer" on the /join page', 'is_public' => 0],
            ['key' => 'maintenance_mode', 'value' => false, 'category' => 'general', 'description' => 'When true, show maintenance page to customers', 'is_public' => 0],
            ['key' => 'maintenance_message', 'value' => 'We are performing scheduled maintenance. Back soon!', 'category' => 'general', 'description' => 'Message shown during maintenance mode', 'is_public' => 1],
            ['key' => 'logo_file_id', 'value' => '', 'category' => 'general', 'description' => 'Platform logo file ID (from files table)', 'is_public' => 1],
            ['key' => 'favicon_url', 'value' => '', 'category' => 'general', 'description' => 'Favicon URL', 'is_public' => 1],

            // ─── orders ───
            ['key' => 'weight_class_light_max_grams', 'value' => 1000, 'category' => 'orders', 'description' => 'Max billable weight (grams) classified as "light"'],
            ['key' => 'weight_class_medium_max_grams', 'value' => 5000, 'category' => 'orders', 'description' => 'Max billable weight (grams) classified as "medium"; above this is "heavy"'],

            // ─── vendors ───
            ['key' => 'min_influencer_commission_percentage', 'value' => 5.00, 'category' => 'vendors', 'description' => 'Minimum commission % a vendor must offer influencers when opting a listing into influencer promotion'],
            ['key' => 'min_affiliate_commission_percentage', 'value' => 5.00, 'category' => 'vendors', 'description' => 'Minimum commission % a vendor must offer affiliates when opting a listing into affiliate promotion'],
            ['key' => 'min_influencer_sample_quota', 'value' => 1, 'category' => 'vendors', 'description' => 'Minimum number of free samples a vendor must allocate for influencer promotion'],
            ['key' => 'min_affiliate_sample_quota', 'value' => 1, 'category' => 'vendors', 'description' => 'Minimum number of free samples a vendor must allocate for affiliate promotion'],

            // ─── customers ────────────────────────────────────────────────────
            ['key' => 'loyalty_referral_bonus_points', 'value' => 50, 'category' => 'customers', 'description' => "Points awarded to referrer after referee's first order"],
            ['key' => 'loyalty_new_customer_bonus_points', 'value' => 50, 'category' => 'customers', 'description' => 'Points awarded to new customer who used referral'],
            ['key' => 'loyalty_tier_silver_points', 'value' => 500, 'category' => 'customers', 'description' => 'Points needed for Silver tier'],
            ['key' => 'loyalty_tier_gold_points', 'value' => 2000, 'category' => 'customers', 'description' => 'Points needed for Gold tier'],
            ['key' => 'loyalty_tier_platinum_points', 'value' => 5000, 'category' => 'customers', 'description' => 'Points needed for Platinum tier'],
            ['key' => 'loyalty_enabled', 'value' => true, 'category' => 'customers', 'description' => 'Master switch — when false, no points are earned or redeemed', 'is_public' => 1],
            ['key' => 'loyalty_earn_rate', 'value' => 0.01, 'category' => 'customers', 'description' => 'Points earned per 1 base-currency unit of sub-order subtotal (e.g. 0.01 = 1 pt per 100 units)', 'is_public' => 1],
            ['key' => 'loyalty_redeem_rate', 'value' => 1.0, 'category' => 'customers', 'description' => 'Base-currency units of discount per 1 loyalty point redeemed (e.g. 1.0 = 1 pt = 1 unit)', 'is_public' => 1],
            ['key' => 'loyalty_min_redeem', 'value' => 100, 'category' => 'customers', 'description' => 'Minimum loyalty points required to apply a redemption at checkout', 'is_public' => 1],

            // ─── notifications ────────────────────────────────────────────────

            // ─── security ─────────────────────────────────────────────────────

            // ─── integrations ─────────────────────────────────────────────────
            ['key' => 'mail_mailer', 'value' => 'smtp', 'category' => 'integrations', 'description' => 'Mail driver (smtp/log/sendmail)'],
            ['key' => 'mail_host', 'value' => '', 'category' => 'integrations', 'description' => 'SMTP host'],
            ['key' => 'mail_port', 'value' => 587, 'category' => 'integrations', 'description' => 'SMTP port'],
            ['key' => 'mail_encryption', 'value' => 'tls', 'category' => 'integrations', 'description' => 'SMTP encryption (tls/ssl/none)'],
            ['key' => 'mail_username', 'value' => '', 'category' => 'integrations', 'description' => 'SMTP username'],
            ['key' => 'mail_password', 'value' => '', 'category' => 'integrations', 'description' => 'SMTP password', 'is_encrypted' => 1],
            ['key' => 'mail_from_address', 'value' => '', 'category' => 'integrations', 'description' => 'Sender email address'],
            ['key' => 'mail_from_name', 'value' => '', 'category' => 'integrations', 'description' => 'Sender display name'],

            // ─── appearance ───────────────────────────────────────────────────
            ['key' => 'primary_color', 'value' => '#0284c7', 'category' => 'appearance', 'description' => 'Primary brand color (hex)', 'is_public' => 1],
            ['key' => 'secondary_color', 'value' => '#0f172a', 'category' => 'appearance', 'description' => 'Secondary brand color (hex)', 'is_public' => 1],
            ['key' => 'footer_text_en', 'value' => '© 2025 Nawy. All rights reserved.', 'category' => 'appearance', 'description' => 'Footer copyright text (English)', 'is_public' => 1],
            ['key' => 'footer_text_ar', 'value' => '© 2025 ناوي. جميع الحقوق محفوظة.', 'category' => 'appearance', 'description' => 'Footer copyright text (Arabic)', 'is_public' => 1],
            ['key' => 'announcement_bar_enabled', 'value' => false, 'category' => 'appearance', 'description' => 'Show announcement bar at top of storefront', 'is_public' => 0],
            ['key' => 'announcement_bar_text_en', 'value' => 'Free shipping on orders over 200 EGP', 'category' => 'appearance', 'description' => 'Announcement bar message (English)', 'is_public' => 1],
            ['key' => 'announcement_bar_text_ar', 'value' => 'شحن مجاني للطلبات فوق ٢٠٠ جنيه', 'category' => 'appearance', 'description' => 'Announcement bar message (Arabic)', 'is_public' => 1],
            ['key' => 'announcement_bar_color', 'value' => '#0284c7', 'category' => 'appearance', 'description' => 'Announcement bar background color', 'is_public' => 1],

            // ─── marketer ─────────────────────────────────────────────────────
            ['key' => 'marketer_campaign_auto_approve_hours', 'value' => 36, 'category' => 'marketer', 'description' => 'Hours before a pending campaign is auto-approved if admin does not act', 'is_public' => 0],
            ['key' => 'marketer_campaign_min_platform_sample_qty', 'value' => 1, 'category' => 'marketer', 'description' => 'Minimum units of the single fixed platform (admin) sample every campaign sets aside, even if the category quantity is lower', 'is_public' => 0],
            ['key' => 'marketer_campaign_min_influencer_sample_qty', 'value' => 1, 'category' => 'marketer', 'description' => 'Minimum units of the sample each selected influencer receives, even if the category quantity is lower', 'is_public' => 0],
            ['key' => 'marketer_invitation_timeout_hours', 'value' => 12, 'category' => 'marketer', 'description' => 'Hours a marketer has to accept or reject a campaign invitation before the system picks a replacement marketer', 'is_public' => 0],
            ['key' => 'marketer_referral_grace_days', 'value' => 7, 'category' => 'marketer', 'description' => 'Days a replaced, timed-out or rejected invitation referral code still credits the original marketer for clicks recorded before the replacement (no new attributions after it)', 'is_public' => 0],
            ['key' => 'marketer_payout_clearing_days', 'value' => 3, 'category' => 'marketer', 'description' => 'Days a marketer commission stays pending after the sale is completed before it becomes withdrawable', 'is_public' => 0],
            ['key' => 'marketer_classified_approval_days', 'value' => 2, 'category' => 'marketer', 'description' => 'Days after a classified listing is marked sold through a campaign before the marketer commission is approved', 'is_public' => 0],
            ['key' => 'marketer_attribution_window_days', 'value' => 7, 'category' => 'marketer', 'description' => 'Days after clicking a referral link during which a purchase or booking is still credited to the marketer', 'is_public' => 0],
            ['key' => 'marketer_peer_campaign_min_stock', 'value' => 1, 'category' => 'marketer', 'description' => 'Minimum available units an influencer own-FBN listing must hold before another influencer can promote it', 'is_public' => 0],
            ['key' => 'marketer_whatsapp_resend_cooldown_minutes', 'value' => 10, 'category' => 'marketer', 'description' => 'Minutes an admin must wait before re-sending the WhatsApp invitation of the same pending invitation', 'is_public' => 0],
            ['key' => 'marketer_replacement_min_accepted_campaigns', 'value' => 0, 'category' => 'marketer', 'description' => 'When replacing a rejected/timed-out marketer, prefer marketers with at least this many accepted campaigns', 'is_public' => 0],
        ];

        foreach ($settings as $data) {
            // Admin-toggled visibility flags must survive a re-seed.
            if (str_starts_with($data['key'], 'join_option_') && Setting::where('key', $data['key'])->exists()) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $data['key']],
                [
                    'value' => $data['value'],
                    'category' => $data['category'],
                    'description' => $data['description'] ?? null,
                    'is_encrypted' => $data['is_encrypted'] ?? 0,
                    'is_public' => $data['is_public'] ?? 0,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
