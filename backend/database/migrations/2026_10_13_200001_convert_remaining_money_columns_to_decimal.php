<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remaining money columns (checkout-adjacent listings/shipping/coupons, ads, bookings, gift cards,
 * delivery-agent COD, tax invoices, payment/refund amounts, ...) become DECIMAL(19,4) so every amount
 * accepts decimals. Companion of 2026_10_13_100001 (marketer fee chain + wallets/payouts/ledger).
 *
 * BIGINT -> DECIMAL is lossless; NULL-ability and defaults are kept; generated columns keep their
 * expression. Already-DECIMAL or missing columns are skipped. Deliberately NOT converted: fx rate
 * numerators/denominators, byte sizes, counters, ids.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private const COLUMNS = [
        'ab_test_results' => ['revenue', 'revenue_per_visitor'],
        'ad_campaign_category_targets' => ['bid_override'],
        'ad_campaign_keywords' => ['bid_override'],
        'ad_campaigns' => ['bid', 'budget_daily', 'budget_spent_today', 'budget_spent_total', 'budget_total'],
        'ad_clicks' => ['cost'],
        'ad_daily_stats' => ['revenue_attributed', 'spend'],
        'ad_impressions' => ['bid_at_impression', 'cost_charged'],
        'ad_packages' => ['price_monthly'],
        'admin_listings' => ['compare_at_price', 'cost_price', 'price'],
        'affiliate_promo_codes' => ['min_order_amount'],
        'banner_placement_definitions' => ['base_rate_weekly'],
        'block_analytics' => ['revenue_attributed'],
        'bookable_unit_availability' => ['price_day_only', 'price_with_overnight'],
        'bookable_unit_time_slots' => ['price'],
        'booking_unit_days' => ['price'],
        'carrier_claims' => ['claimed_amount', 'compensated_amount'],
        'cart_card_offers' => ['cashback_fixed_amount', 'max_cashback_amount', 'min_order_amount'],
        'cart_items' => ['unit_price'],
        'carts' => ['estimated_shipping', 'estimated_tax', 'estimated_total', 'subtotal', 'wallet_amount_to_use'],
        'category_commission_tiers' => ['min_commission', 'price_from', 'price_to'],
        'classified_listings' => ['price'],
        'classified_wanted_listings' => ['budget_max', 'budget_min'],
        'commissions' => ['max_commission', 'min_commission'],
        'country_payment_gateways' => ['fee_fixed', 'max_order', 'min_order'],
        'country_shipping_settings' => ['free_shipping_threshold'],
        'coupon_participation_invitations' => ['min_fee_amount'],
        'coupon_participation_requests' => ['offered_fee_amount'],
        'coupons' => ['min_order_amount'],
        'customer_special_requests' => ['budget'],
        'customer_wallets' => ['balance'],
        'delivery_agent_cod_settlements' => ['discrepancy_amount', 'net_to_remit', 'total_cod_collected', 'total_earnings_owed'],
        'delivery_agent_earnings' => ['amount'],
        'delivery_agent_payouts' => ['deductions', 'gross_earnings', 'net_amount'],
        'delivery_agent_shifts' => ['total_earnings'],
        'delivery_assignments' => ['cod_amount_collected'],
        'delivery_zones' => ['base_delivery_fee', 'cod_fee'],
        'disputes' => ['compensation'],
        'fbn_daily_overage_fees' => ['fee_per_unit', 'total_fee'],
        'fbn_storage_fees' => ['rate_per_unit', 'total_fee'],
        'flash_sale_analytics' => ['discount_given', 'gross_revenue', 'platform_commission', 'revenue_at_normal_price', 'vendor_payout'],
        'flash_sale_orders' => ['flash_price', 'original_price'],
        'flash_sale_price_histories' => ['price'],
        'flash_sale_submissions' => ['flash_price', 'original_price', 'reference_price_30d'],
        'flight_bookings' => ['total_price'],
        'gift_card_batches' => ['amount'],
        'gift_card_transactions' => ['amount', 'balance_after'],
        'gift_cards' => ['amount', 'remaining_balance'],
        'international_shipping_rates' => ['base_fee', 'customs_fee_flat', 'rate_per_kg'],
        'marketer_ad_package_subscriptions' => ['amount_paid', 'price', 'vat_amount'],
        'marketer_ad_packages' => ['price'],
        'marketer_listings' => ['compare_at_price', 'price'],
        'marketplace_shipping_rules' => ['extra_delivery_fee'],
        'open_market_category_commissions' => ['commission_flat_amount'],
        'open_market_listing_prices' => ['base_price', 'max_price', 'min_price'],
        'packaging_supplies' => ['unit_cost'],
        'packaging_supply_countries' => ['unit_cost'],
        'packaging_supply_request_items' => ['line_total', 'unit_cost'],
        'packaging_supply_requests' => ['delivery_fee', 'total_cost'],
        'paid_ad_bookings' => ['agreed_rate', 'budget_amount', 'quoted_amount', 'subscription_charged', 'tax_amount', 'total_charged', 'unit_rate'],
        'paid_ad_charges' => ['amount', 'tax_amount'],
        'paid_ad_daily_stats' => ['spend'],
        'paid_ad_slots' => ['base_rate', 'min_budget'],
        'payment_transactions' => ['amount', 'gateway_amount', 'gateway_fee'],
        'platform_shipping_subsidies' => ['admin_fixed_amount', 'subsidy_cap', 'vendor_fixed_amount'],
        'product_cost_references' => ['landed_cost', 'manufacturer_cost', 'shipping_cost'],
        'product_country_buybox' => ['compare_at_price', 'max_price', 'min_price', 'price'],
        'product_price_history' => ['price'],
        'refunds' => ['amount', 'gateway_fee_deducted', 'net_refund', 'tax_deducted'],
        'return_requests' => ['refund_amount'],
        'shipments' => ['shipping_cost_actual'],
        'shipping_rates' => ['base_fee', 'carrier_rate', 'carrier_rate_per_kg', 'cod_extra_fee', 'free_shipping_threshold', 'rate_per_kg'],
        'shipping_weight_slabs' => ['extra_fee'],
        'subscription_plans' => ['price'],
        'tax_invoices' => ['subtotal', 'tax', 'total'],
        'travel_agency_campaign_offers' => ['budget_per_marketer_cents', 'total_budget_cents', 'total_spent_cents'],
        'travel_bookings' => ['total_price'],
        'travel_package_pricing_tiers' => ['price'],
        'travel_packages' => ['price'],
        'vendor_acquisition_commission_earnings' => ['amount'],
        'vendor_acquisition_commissions' => ['total_earned'],
        'vendor_exceptional_zone_alerts' => ['reported_carrier_fee'],
        'vendor_listings' => ['compare_at_price', 'cost_price', 'first_price', 'price'],
        'vendor_subscription_invoices' => ['amount'],
        'vendors' => ['total_sales'],
        'voucher_redemptions' => ['amount', 'wallet_balance_after'],
        'vouchers' => ['amount'],
        'warehouses' => ['daily_fee_per_unit', 'storage_rate_per_m3_price'],
        'warranty_purchases' => ['price_paid'],
        'countries' => ['cod_max_amount', 'cod_supermall_max_amount'],
        'delivery_agents' => ['base_salary', 'per_delivery_fee'],
        'vendor_city_shipping_surcharges' => ['extra_amount_cents'],
        'warehouse_shipping_surcharges' => ['extra_amount_cents'],
        'warranty_plans' => ['price'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->modify($table, $column, 'DECIMAL(19,4)');
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->modify($table, $column, 'BIGINT');
            }
        }
    }

    private function modify(string $table, string $column, string $type): void
    {
        $info = DB::selectOne(
            'SELECT is_nullable AS nullable, column_default AS dflt, column_type AS ctype, extra AS extra, generation_expression AS gen
             FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );

        $current = $info ? strtolower($info->ctype) : '';
        $convertible = $type === 'BIGINT' ? $current === 'decimal(19,4)' : (bool) preg_match('/^(big)?int\b/', $current);
        if (! $convertible) {
            return;
        }

        // Generated column (refunds.net_refund = amount - fees - tax): keep the expression, only widen the type.
        if (str_contains(strtoupper((string) $info->extra), 'GENERATED')) {
            $storage = str_contains(strtoupper((string) $info->extra), 'STORED') ? 'STORED' : 'VIRTUAL';
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} GENERATED ALWAYS AS ({$info->gen}) {$storage}");

            return;
        }

        $nullable = $info->nullable === 'YES';
        $default = $info->dflt === null
            ? ($nullable ? ' DEFAULT NULL' : '')
            : ' DEFAULT '.(is_numeric($info->dflt) ? ($type === 'BIGINT' ? (int) $info->dflt : $info->dflt) : "'".addslashes($info->dflt)."'");

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} ".($nullable ? 'NULL' : 'NOT NULL').$default);
    }
};
