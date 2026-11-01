<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Drops settings rows that no code reads: security/notifications/orders/vendors
 * knobs that were never wired up, the stub payment-gateway and SMS keys, and
 * unused third-party API keys. All non-public, so no API consumer can depend on them.
 */
return new class extends Migration
{
    /** [key, value, category, description, is_encrypted]; snapshot used by down(). */
    private function rows(): array
    {
        return [
            ['default_country_code', 'egy', 'general', 'Default country site_code for redirect', 0],
            ['platform_base_currency', 'USD', 'general', 'Base currency for exchange rate calculations', 0],
            ['max_cart_items', 50, 'orders', 'Maximum items allowed in a single cart', 0],
            ['cart_expiry_days_guest', 1, 'orders', 'Days before guest cart expires', 0],
            ['cart_expiry_days_customer', 30, 'orders', 'Days before logged-in customer cart expires', 0],
            ['checkout_lock_minutes', 15, 'orders', 'Minutes inventory stays locked during checkout', 0],
            ['sla_ship_hours', 48, 'orders', 'Default hours for vendor to ship after order placed', 0],
            ['sla_warning_hours_before', 2, 'orders', 'Hours before SLA deadline to show warning', 0],
            ['order_cancellation_window_hours', 1, 'orders', 'Hours after placement customer can cancel', 0],
            ['return_window_days', 14, 'orders', 'Days after delivery customer can request return', 0],
            ['cod_fee_flat', 0, 'orders', 'Flat COD fee in cents (0 = free COD)', 0],
            ['cod_shortfall_policy', 'deducted_from_earnings', 'orders', 'How COD collection shortfalls are resolved: deducted_from_earnings (agent bears risk), written_off (platform absorbs), vendor_chargeback (deducted from vendor payout)', 0],
            ['min_order_amount', 0, 'orders', 'Minimum order value in cents (0 = no minimum)', 0],
            ['auto_complete_order_days', 7, 'orders', 'Days after delivery before order auto-completes', 0],
            ['max_order_quantity_per_item', 10, 'orders', 'Maximum quantity of single item per order', 0],
            ['vendor_commission_default', 10, 'vendors', 'Default platform commission % if no rule matches', 0],
            ['new_vendor_listing_review', true, 'vendors', 'When true, new vendors listings need review', 0],
            ['new_vendor_listing_count_threshold', 10, 'vendors', 'Listings count after which vendor is \'established\'', 0],
            ['vendor_auto_approve_rating_threshold', 4.3, 'vendors', 'Min rating for established vendor auto-approve', 0],
            ['vendor_auto_approve_listings_threshold', 100, 'vendors', 'Min active listings for established vendor', 0],
            ['vendor_strikes_auto_suspend', 3, 'vendors', 'Number of active strikes before auto-suspend', 0],
            ['vendor_strike_expiry_days', 90, 'vendors', 'Days after which a strike auto-expires', 0],
            ['vendor_document_expiry_warning_days', 30, 'vendors', 'Days before document expiry to send warning', 0],
            ['vendor_review_sla_days', 5, 'vendors', 'Target days to review new vendor applications', 0],
            ['vendor_min_payout_amount', 10000, 'vendors', 'Minimum payout amount in cents (e.g. 10000 = 100 EGP)', 0],
            ['vendor_payout_processing_days', 3, 'vendors', 'Business days to process approved payouts', 0],
            ['admin_mandated_sample_quota', 0, 'vendors', 'Additional samples auto-reserved for admin use on top of vendor-provided influencer/affiliate samples', 0],
            ['loyalty_points_per_100_egp', 1, 'customers', 'Loyalty points earned per 100 EGP spent', 0],
            ['max_addresses_per_customer', 10, 'customers', 'Maximum saved addresses per customer', 0],
            ['customer_otp_expiry_minutes', 10, 'customers', 'Minutes before phone OTP expires', 0],
            ['notify_admin_new_order', true, 'notifications', 'Email admin on every new order', 0],
            ['notify_admin_new_vendor_application', true, 'notifications', 'Email admin when new vendor applies', 0],
            ['notify_admin_new_dispute', true, 'notifications', 'Email admin when dispute is opened', 0],
            ['notify_admin_sla_breach', true, 'notifications', 'Email admin when vendor breaches SLA', 0],
            ['notify_admin_low_stock', true, 'notifications', 'Email admin when product goes below threshold', 0],
            ['notify_admin_payout_failure', true, 'notifications', 'Email admin when payout processing fails', 0],
            ['admin_notification_email', 'ops@platform.com', 'notifications', 'Email address for admin notifications', 0],
            ['vendor_welcome_email_enabled', true, 'notifications', 'Send welcome email when vendor is approved', 0],
            ['customer_order_email_enabled', true, 'notifications', 'Send order confirmation email to customers', 0],
            ['sms_notifications_enabled', false, 'notifications', 'Enable SMS notifications (requires SMS gateway)', 0],
            ['push_notifications_enabled', true, 'notifications', 'Enable push notifications for mobile app', 0],
            ['max_login_attempts', 5, 'security', 'Failed login attempts before lockout', 0],
            ['login_lockout_minutes', 15, 'security', 'Minutes to lock account after max failed attempts', 0],
            ['admin_session_timeout_minutes', 120, 'security', 'Minutes of inactivity before admin session expires', 0],
            ['require_2fa_admins', false, 'security', 'Require 2FA for all admin logins', 0],
            ['password_min_length', 8, 'security', 'Minimum password length', 0],
            ['api_rate_limit_per_minute', 60, 'security', 'API requests per minute per token', 0],
            ['sanctum_token_expiry_days_web', 30, 'security', 'Customer web token expiry in days', 0],
            ['sanctum_token_expiry_days_app', 90, 'security', 'Customer app token expiry in days', 0],
            ['payment_gateway_provider', 'paymob', 'integrations', 'Active payment gateway (paymob/stripe/tap)', 0],
            ['payment_gateway_live_mode', false, 'integrations', 'When false, use test/sandbox credentials', 0],
            ['payment_gateway_api_key', '', 'integrations', 'Payment gateway API key', 1],
            ['payment_gateway_secret', '', 'integrations', 'Payment gateway secret key', 1],
            ['sms_gateway_provider', 'twilio', 'integrations', 'SMS provider (twilio/vonage/local)', 0],
            ['sms_gateway_api_key', '', 'integrations', 'SMS gateway API key', 1],
            ['firebase_server_key', '', 'integrations', 'Firebase FCM server key for push notifications', 1],
            ['google_maps_api_key', '', 'integrations', 'Google Maps API key for address geocoding', 1],
            ['meilisearch_host', 'http://localhost:7700', 'integrations', 'Meilisearch server host URL', 0],
            ['meilisearch_key', '', 'integrations', 'Meilisearch master key', 1],
            ['exchange_rate_api_key', '', 'integrations', 'API key for automatic exchange rate updates', 1],
        ];
    }

    public function up(): void
    {
        DB::table('settings')
            ->whereIn('key', array_column($this->rows(), 0))
            ->where('is_public', false)
            ->delete();

        Cache::flush();
    }

    public function down(): void
    {
        $now = now();

        DB::table('settings')->insertOrIgnore(array_map(fn (array $r) => [
            'id' => (string) Str::uuid(),
            'key' => $r[0],
            'value' => json_encode($r[1]),
            'category' => $r[2],
            'description' => $r[3],
            'is_encrypted' => (bool) $r[4],
            'is_public' => false,
            'updated_at' => $now,
        ], $this->rows()));
    }
};
