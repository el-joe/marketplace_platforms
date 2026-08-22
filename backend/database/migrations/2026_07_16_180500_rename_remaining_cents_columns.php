<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ab_test_results', function (Blueprint $table) {
            $table->renameColumn('revenue_cents', 'revenue');
            $table->renameColumn('revenue_per_visitor_cents', 'revenue_per_visitor');
        });

        Schema::table('ad_clicks', function (Blueprint $table) {
            $table->renameColumn('cost_cents', 'cost');
        });

        Schema::table('ad_daily_stats', function (Blueprint $table) {
            $table->renameColumn('revenue_attributed_cents', 'revenue_attributed');
            $table->renameColumn('spend_cents', 'spend');
        });

        Schema::table('ad_impressions', function (Blueprint $table) {
            $table->renameColumn('bid_at_impression_cents', 'bid_at_impression');
            $table->renameColumn('cost_charged_cents', 'cost_charged');
        });

        Schema::table('admin_product_listings', function (Blueprint $table) {
            $table->renameColumn('cost_price_cents', 'cost_price');
            $table->renameColumn('price_cents', 'price');
            $table->renameColumn('shipping_cost_cents', 'shipping_cost');
        });

        Schema::table('banner_placement_definitions', function (Blueprint $table) {
            $table->renameColumn('base_rate_weekly_cents', 'base_rate_weekly');
        });

        Schema::table('block_analytics', function (Blueprint $table) {
            $table->renameColumn('revenue_attributed_cents', 'revenue_attributed');
        });

        Schema::table('carrier_claims', function (Blueprint $table) {
            $table->renameColumn('claimed_amount_cents', 'claimed_amount');
            $table->renameColumn('compensated_amount_cents', 'compensated_amount');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('commission_fbn_fixed_cents', 'commission_fbn_fixed');
            $table->renameColumn('commission_fbp_fixed_cents', 'commission_fbp_fixed');
        });

        Schema::table('classified_listings', function (Blueprint $table) {
            $table->renameColumn('price_cents', 'price');
        });

        Schema::table('country_payment_methods', function (Blueprint $table) {
            $table->renameColumn('fee_fixed_cents', 'fee_fixed');
            $table->renameColumn('max_order_cents', 'max_order');
            $table->renameColumn('min_order_cents', 'min_order');
        });

        Schema::table('country_shipping_settings', function (Blueprint $table) {
            $table->renameColumn('free_shipping_threshold_cents', 'free_shipping_threshold');
        });

        Schema::table('delivery_agent_cod_settlements', function (Blueprint $table) {
            $table->renameColumn('discrepancy_amount_cents', 'discrepancy_amount');
            $table->renameColumn('net_to_remit_cents', 'net_to_remit');
            $table->renameColumn('total_cod_collected_cents', 'total_cod_collected');
            $table->renameColumn('total_earnings_owed_cents', 'total_earnings_owed');
        });

        Schema::table('delivery_agent_earnings', function (Blueprint $table) {
            $table->renameColumn('amount_cents', 'amount');
        });

        Schema::table('delivery_agent_payouts', function (Blueprint $table) {
            $table->renameColumn('deductions_cents', 'deductions');
            $table->renameColumn('gross_earnings_cents', 'gross_earnings');
            $table->renameColumn('net_amount_cents', 'net_amount');
        });

        Schema::table('delivery_agent_shifts', function (Blueprint $table) {
            $table->renameColumn('total_earnings_cents', 'total_earnings');
        });

        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->renameColumn('cod_amount_collected_cents', 'cod_amount_collected');
        });

        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->renameColumn('base_delivery_fee_cents', 'base_delivery_fee');
            $table->renameColumn('cod_fee_cents', 'cod_fee');
        });

        Schema::table('disputes', function (Blueprint $table) {
            $table->renameColumn('compensation_cents', 'compensation');
        });

        Schema::table('fbn_storage_fees', function (Blueprint $table) {
            $table->renameColumn('rate_per_unit_cents', 'rate_per_unit');
            $table->renameColumn('total_fee_cents', 'total_fee');
        });

        Schema::table('gift_card_transactions', function (Blueprint $table) {
            $table->renameColumn('amount_cents', 'amount');
            $table->renameColumn('balance_after_cents', 'balance_after');
        });

        if (Schema::hasTable('marketer_campaigns')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->renameColumn('budget_cents', 'budget');
                $table->renameColumn('budget_spent_cents', 'budget_spent');
                $table->renameColumn('total_revenue_cents', 'total_revenue');
            });
        }

        if (Schema::hasTable('marketer_conversions')) {
            Schema::table('marketer_conversions', function (Blueprint $table) {
                $table->renameColumn('commission_amount_cents', 'commission_amount');
                $table->renameColumn('order_value_cents', 'order_value');
            });
        }

        if (Schema::hasTable('marketer_payouts')) {
            Schema::table('marketer_payouts', function (Blueprint $table) {
                $table->renameColumn('gross_commission_cents', 'gross_commission');
                $table->renameColumn('net_amount_cents', 'net_amount');
                $table->renameColumn('tax_deduction_cents', 'tax_deduction');
            });
        }

        if (Schema::hasTable('marketer_sample_items')) {
            Schema::table('marketer_sample_items', function (Blueprint $table) {
                $table->renameColumn('sample_cost_cents', 'sample_cost');
            });
        }

        if (Schema::hasTable('marketer_secret_promotions')) {
            Schema::table('marketer_secret_promotions', function (Blueprint $table) {
                $table->renameColumn('product_value_cents', 'product_value');
            });
        }

        if (Schema::hasTable('marketers')) {
            Schema::table('marketers', function (Blueprint $table) {
                $table->renameColumn('total_earnings_cents', 'total_earnings');
            });
        }

        Schema::table('marketplace_shipping_rules', function (Blueprint $table) {
            $table->renameColumn('extra_delivery_fee_cents', 'extra_delivery_fee');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('commission_fixed_cents', 'commission_fixed');
        });

        Schema::table('packaging_supplies', function (Blueprint $table) {
            $table->renameColumn('unit_cost_cents', 'unit_cost');
        });

        Schema::table('packaging_supply_request_items', function (Blueprint $table) {
            $table->renameColumn('line_total_cents', 'line_total');
            $table->renameColumn('unit_cost_cents', 'unit_cost');
        });

        Schema::table('packaging_supply_requests', function (Blueprint $table) {
            $table->renameColumn('delivery_fee_cents', 'delivery_fee');
            $table->renameColumn('total_cost_cents', 'total_cost');
        });

        Schema::table('paid_ad_bookings', function (Blueprint $table) {
            $table->renameColumn('agreed_rate_cents', 'agreed_rate');
        });

        Schema::table('paid_ad_slots', function (Blueprint $table) {
            $table->renameColumn('base_rate_cents', 'base_rate');
        });

        Schema::table('paid_banner_bookings', function (Blueprint $table) {
            $table->renameColumn('rate_cents', 'rate');
            $table->renameColumn('total_charged_cents', 'total_charged');
        });

        Schema::table('product_cost_references', function (Blueprint $table) {
            $table->renameColumn('landed_cost_cents', 'landed_cost');
            $table->renameColumn('manufacturer_cost_cents', 'manufacturer_cost');
            $table->renameColumn('shipping_cost_cents', 'shipping_cost');
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->renameColumn('refund_amount_cents', 'refund_amount');
        });

        Schema::table('shipping_weight_slabs', function (Blueprint $table) {
            $table->renameColumn('extra_fee_cents', 'extra_fee');
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->renameColumn('admin_subsidy_cents', 'admin_subsidy');
            $table->renameColumn('vendor_deduction_cents', 'vendor_deduction');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->renameColumn('price_cents', 'price');
        });

        Schema::table('travel_bookings', function (Blueprint $table) {
            $table->renameColumn('total_price_cents', 'total_price');
        });

        Schema::table('travel_package_pricing_tiers', function (Blueprint $table) {
            $table->renameColumn('price_cents', 'price');
        });

        Schema::table('travel_packages', function (Blueprint $table) {
            $table->renameColumn('price_cents', 'price');
        });

        Schema::table('vendor_campaign_offers', function (Blueprint $table) {
            $table->renameColumn('budget_per_marketer_cents', 'budget_per_marketer');
            $table->renameColumn('total_budget_cents', 'total_budget');
            $table->renameColumn('total_spent_cents', 'total_spent');
        });

        Schema::table('vendor_fbp_subsidy_settings', function (Blueprint $table) {
            $table->renameColumn('admin_subsidy_cents', 'admin_subsidy');
            $table->renameColumn('full_coverage_threshold_cents', 'full_coverage_threshold');
        });

        Schema::table('vendor_subscription_invoices', function (Blueprint $table) {
            $table->renameColumn('amount_cents', 'amount');
        });

        Schema::table('vendor_subsidy_settings', function (Blueprint $table) {
            $table->renameColumn('admin_support_cents', 'admin_support');
            $table->renameColumn('vendor_share_cents', 'vendor_share');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->renameColumn('amount_cents', 'amount');
            $table->renameColumn('balance_after_cents', 'balance_after');
        });

        Schema::table('wallet_withdrawal_requests', function (Blueprint $table) {
            $table->renameColumn('amount_cents', 'amount');
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->renameColumn('balance_cents', 'balance');
            $table->renameColumn('pending_balance_cents', 'pending_balance');
        });

        Schema::table('warranty_plans', function (Blueprint $table) {
            $table->renameColumn('price_cents', 'price');
        });

        Schema::table('warranty_purchases', function (Blueprint $table) {
            $table->renameColumn('price_paid_cents', 'price_paid');
        });

        // refunds.net_refund_cents is a VIRTUAL GENERATED column referencing the two
        // columns below. Drop it first, rename the underlying columns, then recreate
        // the generated column referencing the new names.
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn('net_refund_cents');
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->renameColumn('gateway_fee_deducted_cents', 'gateway_fee_deducted');
            $table->renameColumn('tax_deducted_cents', 'tax_deducted');
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('net_refund')
                ->virtualAs('amount - gateway_fee_deducted - tax_deducted')
                ->comment('Actual amount returned to customer after deductions')
                ->after('tax_deducted');
        });

        DB::statement("UPDATE settings SET `key` = 'min_order_amount' WHERE `key` = 'min_order_amount_cents'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE settings SET `key` = 'min_order_amount_cents' WHERE `key` = 'min_order_amount'");

        // refunds: reverse in inverse order
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn('net_refund');
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->renameColumn('gateway_fee_deducted', 'gateway_fee_deducted_cents');
            $table->renameColumn('tax_deducted', 'tax_deducted_cents');
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('net_refund_cents')
                ->virtualAs('amount - gateway_fee_deducted_cents - tax_deducted_cents')
                ->comment('Actual amount returned to customer after deductions')
                ->after('tax_deducted_cents');
        });

        Schema::table('ab_test_results', function (Blueprint $table) {
            $table->renameColumn('revenue', 'revenue_cents');
            $table->renameColumn('revenue_per_visitor', 'revenue_per_visitor_cents');
        });

        Schema::table('ad_clicks', function (Blueprint $table) {
            $table->renameColumn('cost', 'cost_cents');
        });

        Schema::table('ad_daily_stats', function (Blueprint $table) {
            $table->renameColumn('revenue_attributed', 'revenue_attributed_cents');
            $table->renameColumn('spend', 'spend_cents');
        });

        Schema::table('ad_impressions', function (Blueprint $table) {
            $table->renameColumn('bid_at_impression', 'bid_at_impression_cents');
            $table->renameColumn('cost_charged', 'cost_charged_cents');
        });

        Schema::table('admin_product_listings', function (Blueprint $table) {
            $table->renameColumn('cost_price', 'cost_price_cents');
            $table->renameColumn('price', 'price_cents');
            $table->renameColumn('shipping_cost', 'shipping_cost_cents');
        });

        Schema::table('banner_placement_definitions', function (Blueprint $table) {
            $table->renameColumn('base_rate_weekly', 'base_rate_weekly_cents');
        });

        Schema::table('block_analytics', function (Blueprint $table) {
            $table->renameColumn('revenue_attributed', 'revenue_attributed_cents');
        });

        Schema::table('carrier_claims', function (Blueprint $table) {
            $table->renameColumn('claimed_amount', 'claimed_amount_cents');
            $table->renameColumn('compensated_amount', 'compensated_amount_cents');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('commission_fbn_fixed', 'commission_fbn_fixed_cents');
            $table->renameColumn('commission_fbp_fixed', 'commission_fbp_fixed_cents');
        });

        Schema::table('classified_listings', function (Blueprint $table) {
            $table->renameColumn('price', 'price_cents');
        });

        Schema::table('country_payment_methods', function (Blueprint $table) {
            $table->renameColumn('fee_fixed', 'fee_fixed_cents');
            $table->renameColumn('max_order', 'max_order_cents');
            $table->renameColumn('min_order', 'min_order_cents');
        });

        Schema::table('country_shipping_settings', function (Blueprint $table) {
            $table->renameColumn('free_shipping_threshold', 'free_shipping_threshold_cents');
        });

        Schema::table('delivery_agent_cod_settlements', function (Blueprint $table) {
            $table->renameColumn('discrepancy_amount', 'discrepancy_amount_cents');
            $table->renameColumn('net_to_remit', 'net_to_remit_cents');
            $table->renameColumn('total_cod_collected', 'total_cod_collected_cents');
            $table->renameColumn('total_earnings_owed', 'total_earnings_owed_cents');
        });

        Schema::table('delivery_agent_earnings', function (Blueprint $table) {
            $table->renameColumn('amount', 'amount_cents');
        });

        Schema::table('delivery_agent_payouts', function (Blueprint $table) {
            $table->renameColumn('deductions', 'deductions_cents');
            $table->renameColumn('gross_earnings', 'gross_earnings_cents');
            $table->renameColumn('net_amount', 'net_amount_cents');
        });

        Schema::table('delivery_agent_shifts', function (Blueprint $table) {
            $table->renameColumn('total_earnings', 'total_earnings_cents');
        });

        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->renameColumn('cod_amount_collected', 'cod_amount_collected_cents');
        });

        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->renameColumn('base_delivery_fee', 'base_delivery_fee_cents');
            $table->renameColumn('cod_fee', 'cod_fee_cents');
        });

        Schema::table('disputes', function (Blueprint $table) {
            $table->renameColumn('compensation', 'compensation_cents');
        });

        Schema::table('fbn_storage_fees', function (Blueprint $table) {
            $table->renameColumn('rate_per_unit', 'rate_per_unit_cents');
            $table->renameColumn('total_fee', 'total_fee_cents');
        });

        Schema::table('gift_card_transactions', function (Blueprint $table) {
            $table->renameColumn('amount', 'amount_cents');
            $table->renameColumn('balance_after', 'balance_after_cents');
        });

        if (Schema::hasTable('marketer_campaigns')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->renameColumn('budget', 'budget_cents');
                $table->renameColumn('budget_spent', 'budget_spent_cents');
                $table->renameColumn('total_revenue', 'total_revenue_cents');
            });
        }

        if (Schema::hasTable('marketer_conversions')) {
            Schema::table('marketer_conversions', function (Blueprint $table) {
                $table->renameColumn('commission_amount', 'commission_amount_cents');
                $table->renameColumn('order_value', 'order_value_cents');
            });
        }

        if (Schema::hasTable('marketer_payouts')) {
            Schema::table('marketer_payouts', function (Blueprint $table) {
                $table->renameColumn('gross_commission', 'gross_commission_cents');
                $table->renameColumn('net_amount', 'net_amount_cents');
                $table->renameColumn('tax_deduction', 'tax_deduction_cents');
            });
        }

        if (Schema::hasTable('marketer_sample_items')) {
            Schema::table('marketer_sample_items', function (Blueprint $table) {
                $table->renameColumn('sample_cost', 'sample_cost_cents');
            });
        }

        if (Schema::hasTable('marketer_secret_promotions')) {
            Schema::table('marketer_secret_promotions', function (Blueprint $table) {
                $table->renameColumn('product_value', 'product_value_cents');
            });
        }

        if (Schema::hasTable('marketers')) {
            Schema::table('marketers', function (Blueprint $table) {
                $table->renameColumn('total_earnings', 'total_earnings_cents');
            });
        }

        Schema::table('marketplace_shipping_rules', function (Blueprint $table) {
            $table->renameColumn('extra_delivery_fee', 'extra_delivery_fee_cents');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('commission_fixed', 'commission_fixed_cents');
        });

        Schema::table('packaging_supplies', function (Blueprint $table) {
            $table->renameColumn('unit_cost', 'unit_cost_cents');
        });

        Schema::table('packaging_supply_request_items', function (Blueprint $table) {
            $table->renameColumn('line_total', 'line_total_cents');
            $table->renameColumn('unit_cost', 'unit_cost_cents');
        });

        Schema::table('packaging_supply_requests', function (Blueprint $table) {
            $table->renameColumn('delivery_fee', 'delivery_fee_cents');
            $table->renameColumn('total_cost', 'total_cost_cents');
        });

        Schema::table('paid_ad_bookings', function (Blueprint $table) {
            $table->renameColumn('agreed_rate', 'agreed_rate_cents');
        });

        Schema::table('paid_ad_slots', function (Blueprint $table) {
            $table->renameColumn('base_rate', 'base_rate_cents');
        });

        Schema::table('paid_banner_bookings', function (Blueprint $table) {
            $table->renameColumn('rate', 'rate_cents');
            $table->renameColumn('total_charged', 'total_charged_cents');
        });

        Schema::table('product_cost_references', function (Blueprint $table) {
            $table->renameColumn('landed_cost', 'landed_cost_cents');
            $table->renameColumn('manufacturer_cost', 'manufacturer_cost_cents');
            $table->renameColumn('shipping_cost', 'shipping_cost_cents');
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->renameColumn('refund_amount', 'refund_amount_cents');
        });

        Schema::table('shipping_weight_slabs', function (Blueprint $table) {
            $table->renameColumn('extra_fee', 'extra_fee_cents');
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->renameColumn('admin_subsidy', 'admin_subsidy_cents');
            $table->renameColumn('vendor_deduction', 'vendor_deduction_cents');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->renameColumn('price', 'price_cents');
        });

        Schema::table('travel_bookings', function (Blueprint $table) {
            $table->renameColumn('total_price', 'total_price_cents');
        });

        Schema::table('travel_package_pricing_tiers', function (Blueprint $table) {
            $table->renameColumn('price', 'price_cents');
        });

        Schema::table('travel_packages', function (Blueprint $table) {
            $table->renameColumn('price', 'price_cents');
        });

        Schema::table('vendor_campaign_offers', function (Blueprint $table) {
            $table->renameColumn('budget_per_marketer', 'budget_per_marketer_cents');
            $table->renameColumn('total_budget', 'total_budget_cents');
            $table->renameColumn('total_spent', 'total_spent_cents');
        });

        Schema::table('vendor_fbp_subsidy_settings', function (Blueprint $table) {
            $table->renameColumn('admin_subsidy', 'admin_subsidy_cents');
            $table->renameColumn('full_coverage_threshold', 'full_coverage_threshold_cents');
        });

        Schema::table('vendor_subscription_invoices', function (Blueprint $table) {
            $table->renameColumn('amount', 'amount_cents');
        });

        Schema::table('vendor_subsidy_settings', function (Blueprint $table) {
            $table->renameColumn('admin_support', 'admin_support_cents');
            $table->renameColumn('vendor_share', 'vendor_share_cents');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->renameColumn('amount', 'amount_cents');
            $table->renameColumn('balance_after', 'balance_after_cents');
        });

        Schema::table('wallet_withdrawal_requests', function (Blueprint $table) {
            $table->renameColumn('amount', 'amount_cents');
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->renameColumn('balance', 'balance_cents');
            $table->renameColumn('pending_balance', 'pending_balance_cents');
        });

        Schema::table('warranty_plans', function (Blueprint $table) {
            $table->renameColumn('price', 'price_cents');
        });

        Schema::table('warranty_purchases', function (Blueprint $table) {
            $table->renameColumn('price_paid', 'price_paid_cents');
        });

    }
};
