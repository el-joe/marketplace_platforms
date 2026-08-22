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
            ['key' => 'site_name', 'value' => 'Noon', 'category' => 'general', 'description' => 'Platform display name', 'is_public' => 1],
            ['key' => 'site_tagline', 'value' => 'Anything you need, Noon delivers', 'category' => 'general', 'description' => 'Short tagline shown on homepage', 'is_public' => 1],
            ['key' => 'support_email', 'value' => 'support@platform.com', 'category' => 'general', 'description' => 'Customer-facing support email', 'is_public' => 1],
            ['key' => 'support_phone', 'value' => '+20 2 12345678', 'category' => 'general', 'description' => 'Customer-facing support phone', 'is_public' => 1],
            ['key' => 'default_country_code', 'value' => 'egy', 'category' => 'general', 'description' => 'Default country site_code for redirect', 'is_public' => 0],
            ['key' => 'default_locale', 'value' => 'ar', 'category' => 'general', 'description' => 'Default language (en or ar)', 'is_public' => 1],
            ['key' => 'platform_base_currency', 'value' => 'USD', 'category' => 'general', 'description' => 'Base currency for exchange rate calculations', 'is_public' => 0],
            ['key' => 'maintenance_mode', 'value' => false, 'category' => 'general', 'description' => 'When true, show maintenance page to customers', 'is_public' => 0],
            ['key' => 'maintenance_message', 'value' => 'We are performing scheduled maintenance. Back soon!', 'category' => 'general', 'description' => 'Message shown during maintenance mode', 'is_public' => 1],
            ['key' => 'logo_file_id', 'value' => '', 'category' => 'general', 'description' => 'Platform logo file ID (from files table)', 'is_public' => 1],
            ['key' => 'favicon_url', 'value' => '', 'category' => 'general', 'description' => 'Favicon URL', 'is_public' => 1],

            // ─── orders ───────────────────────────────────────────────────────
            ['key' => 'max_cart_items', 'value' => 50, 'category' => 'orders', 'description' => 'Maximum items allowed in a single cart'],
            ['key' => 'cart_expiry_days_guest', 'value' => 1, 'category' => 'orders', 'description' => 'Days before guest cart expires'],
            ['key' => 'cart_expiry_days_customer', 'value' => 30, 'category' => 'orders', 'description' => 'Days before logged-in customer cart expires'],
            ['key' => 'checkout_lock_minutes', 'value' => 15, 'category' => 'orders', 'description' => 'Minutes inventory stays locked during checkout'],
            ['key' => 'sla_ship_hours', 'value' => 48, 'category' => 'orders', 'description' => 'Default hours for vendor to ship after order placed'],
            ['key' => 'sla_warning_hours_before', 'value' => 2, 'category' => 'orders', 'description' => 'Hours before SLA deadline to show warning'],
            ['key' => 'order_cancellation_window_hours', 'value' => 1, 'category' => 'orders', 'description' => 'Hours after placement customer can cancel'],
            ['key' => 'return_window_days', 'value' => 14, 'category' => 'orders', 'description' => 'Days after delivery customer can request return'],
            ['key' => 'cod_fee_flat', 'value' => 0, 'category' => 'orders', 'description' => 'Flat COD fee in cents (0 = free COD)'],
            [
                'key'         => 'cod_shortfall_policy',
                'value'       => 'deducted_from_earnings',
                'category'    => 'orders',
                'description' => 'How COD collection shortfalls are resolved: deducted_from_earnings (agent bears risk), written_off (platform absorbs), vendor_chargeback (deducted from vendor payout)',
                'is_public'   => 0,
            ],
            ['key' => 'min_order_amount', 'value' => 0, 'category' => 'orders', 'description' => 'Minimum order value in cents (0 = no minimum)'],
            ['key' => 'auto_complete_order_days', 'value' => 7, 'category' => 'orders', 'description' => 'Days after delivery before order auto-completes'],
            ['key' => 'max_order_quantity_per_item', 'value' => 10, 'category' => 'orders', 'description' => 'Maximum quantity of single item per order'],
            ['key' => 'weight_class_light_max_grams', 'value' => 1000, 'category' => 'orders', 'description' => 'Max billable weight (grams) classified as "light"'],
            ['key' => 'weight_class_medium_max_grams', 'value' => 5000, 'category' => 'orders', 'description' => 'Max billable weight (grams) classified as "medium"; above this is "heavy"'],

            // ─── vendors ──────────────────────────────────────────────────────
            ['key' => 'vendor_commission_default', 'value' => 10.00, 'category' => 'vendors', 'description' => 'Default platform commission % if no rule matches'],
            ['key' => 'new_vendor_listing_review', 'value' => true, 'category' => 'vendors', 'description' => 'When true, new vendors listings need review'],
            ['key' => 'new_vendor_listing_count_threshold', 'value' => 10, 'category' => 'vendors', 'description' => "Listings count after which vendor is 'established'"],
            ['key' => 'vendor_auto_approve_rating_threshold', 'value' => 4.3, 'category' => 'vendors', 'description' => 'Min rating for established vendor auto-approve'],
            ['key' => 'vendor_auto_approve_listings_threshold', 'value' => 100, 'category' => 'vendors', 'description' => 'Min active listings for established vendor'],
            ['key' => 'vendor_strikes_auto_suspend', 'value' => 3, 'category' => 'vendors', 'description' => 'Number of active strikes before auto-suspend'],
            ['key' => 'vendor_strike_expiry_days', 'value' => 90, 'category' => 'vendors', 'description' => 'Days after which a strike auto-expires'],
            ['key' => 'vendor_document_expiry_warning_days', 'value' => 30, 'category' => 'vendors', 'description' => 'Days before document expiry to send warning'],
            ['key' => 'vendor_review_sla_days', 'value' => 5, 'category' => 'vendors', 'description' => 'Target days to review new vendor applications'],
            ['key' => 'vendor_min_payout_amount', 'value' => 10000, 'category' => 'vendors', 'description' => 'Minimum payout amount in cents (e.g. 10000 = 100 EGP)'],
            ['key' => 'vendor_payout_processing_days', 'value' => 3, 'category' => 'vendors', 'description' => 'Business days to process approved payouts'],
            ['key' => 'min_influencer_commission_percentage', 'value' => 5.00, 'category' => 'vendors', 'description' => 'Minimum commission % a vendor must offer influencers when opting a listing into influencer promotion'],
            ['key' => 'min_affiliate_commission_percentage', 'value' => 5.00, 'category' => 'vendors', 'description' => 'Minimum commission % a vendor must offer affiliates when opting a listing into affiliate promotion'],
            ['key' => 'min_influencer_sample_quota', 'value' => 1, 'category' => 'vendors', 'description' => 'Minimum number of free samples a vendor must allocate for influencer promotion'],
            ['key' => 'min_affiliate_sample_quota', 'value' => 1, 'category' => 'vendors', 'description' => 'Minimum number of free samples a vendor must allocate for affiliate promotion'],
            ['key' => 'admin_mandated_sample_quota', 'value' => 0, 'category' => 'vendors', 'description' => 'Additional samples auto-reserved for admin use on top of vendor-provided influencer/affiliate samples'],

            // ─── customers ────────────────────────────────────────────────────
            ['key' => 'loyalty_points_per_100_egp', 'value' => 1, 'category' => 'customers', 'description' => 'Loyalty points earned per 100 EGP spent'],
            ['key' => 'loyalty_referral_bonus_points', 'value' => 50, 'category' => 'customers', 'description' => "Points awarded to referrer after referee's first order"],
            ['key' => 'loyalty_new_customer_bonus_points', 'value' => 50, 'category' => 'customers', 'description' => 'Points awarded to new customer who used referral'],
            ['key' => 'loyalty_tier_silver_points', 'value' => 500, 'category' => 'customers', 'description' => 'Points needed for Silver tier'],
            ['key' => 'loyalty_tier_gold_points', 'value' => 2000, 'category' => 'customers', 'description' => 'Points needed for Gold tier'],
            ['key' => 'loyalty_tier_platinum_points', 'value' => 5000, 'category' => 'customers', 'description' => 'Points needed for Platinum tier'],
            ['key' => 'loyalty_enabled', 'value' => true, 'category' => 'customers', 'description' => 'Master switch — when false, no points are earned or redeemed', 'is_public' => 1],
            ['key' => 'loyalty_earn_rate', 'value' => 0.01, 'category' => 'customers', 'description' => 'Points earned per 1 base-currency unit of sub-order subtotal (e.g. 0.01 = 1 pt per 100 units)', 'is_public' => 1],
            ['key' => 'loyalty_redeem_rate', 'value' => 1.0, 'category' => 'customers', 'description' => 'Base-currency units of discount per 1 loyalty point redeemed (e.g. 1.0 = 1 pt = 1 unit)', 'is_public' => 1],
            ['key' => 'loyalty_min_redeem', 'value' => 100, 'category' => 'customers', 'description' => 'Minimum loyalty points required to apply a redemption at checkout', 'is_public' => 1],
            ['key' => 'max_addresses_per_customer', 'value' => 10, 'category' => 'customers', 'description' => 'Maximum saved addresses per customer'],
            ['key' => 'customer_otp_expiry_minutes', 'value' => 10, 'category' => 'customers', 'description' => 'Minutes before phone OTP expires'],

            // ─── notifications ────────────────────────────────────────────────
            ['key' => 'notify_admin_new_order', 'value' => true, 'category' => 'notifications', 'description' => 'Email admin on every new order'],
            ['key' => 'notify_admin_new_vendor_application', 'value' => true, 'category' => 'notifications', 'description' => 'Email admin when new vendor applies'],
            ['key' => 'notify_admin_new_dispute', 'value' => true, 'category' => 'notifications', 'description' => 'Email admin when dispute is opened'],
            ['key' => 'notify_admin_sla_breach', 'value' => true, 'category' => 'notifications', 'description' => 'Email admin when vendor breaches SLA'],
            ['key' => 'notify_admin_low_stock', 'value' => true, 'category' => 'notifications', 'description' => 'Email admin when product goes below threshold'],
            ['key' => 'notify_admin_payout_failure', 'value' => true, 'category' => 'notifications', 'description' => 'Email admin when payout processing fails'],
            ['key' => 'admin_notification_email', 'value' => 'ops@platform.com', 'category' => 'notifications', 'description' => 'Email address for admin notifications'],
            ['key' => 'vendor_welcome_email_enabled', 'value' => true, 'category' => 'notifications', 'description' => 'Send welcome email when vendor is approved'],
            ['key' => 'customer_order_email_enabled', 'value' => true, 'category' => 'notifications', 'description' => 'Send order confirmation email to customers'],
            ['key' => 'sms_notifications_enabled', 'value' => false, 'category' => 'notifications', 'description' => 'Enable SMS notifications (requires SMS gateway)'],
            ['key' => 'push_notifications_enabled', 'value' => true, 'category' => 'notifications', 'description' => 'Enable push notifications for mobile app'],

            // ─── security ─────────────────────────────────────────────────────
            ['key' => 'max_login_attempts', 'value' => 5, 'category' => 'security', 'description' => 'Failed login attempts before lockout'],
            ['key' => 'login_lockout_minutes', 'value' => 15, 'category' => 'security', 'description' => 'Minutes to lock account after max failed attempts'],
            ['key' => 'admin_session_timeout_minutes', 'value' => 120, 'category' => 'security', 'description' => 'Minutes of inactivity before admin session expires'],
            ['key' => 'require_2fa_admins', 'value' => false, 'category' => 'security', 'description' => 'Require 2FA for all admin logins'],
            ['key' => 'password_min_length', 'value' => 8, 'category' => 'security', 'description' => 'Minimum password length'],
            ['key' => 'api_rate_limit_per_minute', 'value' => 60, 'category' => 'security', 'description' => 'API requests per minute per token'],
            ['key' => 'sanctum_token_expiry_days_web', 'value' => 30, 'category' => 'security', 'description' => 'Customer web token expiry in days'],
            ['key' => 'sanctum_token_expiry_days_app', 'value' => 90, 'category' => 'security', 'description' => 'Customer app token expiry in days'],

            // ─── integrations ─────────────────────────────────────────────────
            ['key' => 'payment_gateway_provider', 'value' => 'paymob', 'category' => 'integrations', 'description' => 'Active payment gateway (paymob/stripe/tap)', 'is_encrypted' => 0],
            ['key' => 'payment_gateway_live_mode', 'value' => false, 'category' => 'integrations', 'description' => 'When false, use test/sandbox credentials'],
            ['key' => 'payment_gateway_api_key', 'value' => '', 'category' => 'integrations', 'description' => 'Payment gateway API key', 'is_encrypted' => 1],
            ['key' => 'payment_gateway_secret', 'value' => '', 'category' => 'integrations', 'description' => 'Payment gateway secret key', 'is_encrypted' => 1],
            ['key' => 'sms_gateway_provider', 'value' => 'twilio', 'category' => 'integrations', 'description' => 'SMS provider (twilio/vonage/local)'],
            ['key' => 'sms_gateway_api_key', 'value' => '', 'category' => 'integrations', 'description' => 'SMS gateway API key', 'is_encrypted' => 1],
            ['key' => 'firebase_server_key', 'value' => '', 'category' => 'integrations', 'description' => 'Firebase FCM server key for push notifications', 'is_encrypted' => 1],
            ['key' => 'google_maps_api_key', 'value' => '', 'category' => 'integrations', 'description' => 'Google Maps API key for address geocoding', 'is_encrypted' => 1],
            ['key' => 'meilisearch_host', 'value' => 'http://localhost:7700', 'category' => 'integrations', 'description' => 'Meilisearch server host URL'],
            ['key' => 'meilisearch_key', 'value' => '', 'category' => 'integrations', 'description' => 'Meilisearch master key', 'is_encrypted' => 1],
            ['key' => 'exchange_rate_api_key', 'value' => '', 'category' => 'integrations', 'description' => 'API key for automatic exchange rate updates', 'is_encrypted' => 1],

            // ─── appearance ───────────────────────────────────────────────────
            ['key' => 'primary_color', 'value' => '#0284c7', 'category' => 'appearance', 'description' => 'Primary brand color (hex)', 'is_public' => 1],
            ['key' => 'secondary_color', 'value' => '#0f172a', 'category' => 'appearance', 'description' => 'Secondary brand color (hex)', 'is_public' => 1],
            ['key' => 'footer_text_en', 'value' => '© 2025 Noon. All rights reserved.', 'category' => 'appearance', 'description' => 'Footer copyright text (English)', 'is_public' => 1],
            ['key' => 'footer_text_ar', 'value' => '© 2025 نون. جميع الحقوق محفوظة.', 'category' => 'appearance', 'description' => 'Footer copyright text (Arabic)', 'is_public' => 1],
            ['key' => 'announcement_bar_enabled', 'value' => false, 'category' => 'appearance', 'description' => 'Show announcement bar at top of storefront', 'is_public' => 0],
            ['key' => 'announcement_bar_text_en', 'value' => 'Free shipping on orders over 200 EGP', 'category' => 'appearance', 'description' => 'Announcement bar message (English)', 'is_public' => 1],
            ['key' => 'announcement_bar_text_ar', 'value' => 'شحن مجاني للطلبات فوق ٢٠٠ جنيه', 'category' => 'appearance', 'description' => 'Announcement bar message (Arabic)', 'is_public' => 1],
            ['key' => 'announcement_bar_color', 'value' => '#0284c7', 'category' => 'appearance', 'description' => 'Announcement bar background color', 'is_public' => 1],

            // ─── marketer ─────────────────────────────────────────────────────
            ['key' => 'marketer_campaign_auto_approve_hours', 'value' => 36, 'category' => 'marketer', 'description' => 'Hours before a pending campaign is auto-approved if admin does not act', 'is_public' => 0],
            ['key' => 'marketer_invitation_timeout_hours', 'value' => 12, 'category' => 'marketer', 'description' => 'Hours a marketer has to respond to a campaign invitation before auto-rejection', 'is_public' => 0],
            ['key' => 'marketer_replacement_min_accepted_campaigns', 'value' => 0, 'category' => 'marketer', 'description' => 'When replacing a rejected/timed-out marketer, prefer marketers with at least this many accepted campaigns', 'is_public' => 0],
        ];

        foreach ($settings as $data) {
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
