<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ab_test_results
        Schema::table('ab_test_results', function (Blueprint $table) {
            if (Schema::hasColumn('ab_test_results', 'revenue_cents')) {
                $table->renameColumn('revenue_cents', 'revenue');
            }
            if (Schema::hasColumn('ab_test_results', 'revenue_per_visitor_cents')) {
                $table->renameColumn('revenue_per_visitor_cents', 'revenue_per_visitor');
            }
        });

        // ad_clicks
        Schema::table('ad_clicks', function (Blueprint $table) {
            if (Schema::hasColumn('ad_clicks', 'cost_cents')) {
                $table->renameColumn('cost_cents', 'cost');
            }
        });

        // ad_daily_stats
        Schema::table('ad_daily_stats', function (Blueprint $table) {
            if (Schema::hasColumn('ad_daily_stats', 'spend_cents')) {
                $table->renameColumn('spend_cents', 'spend');
            }
            if (Schema::hasColumn('ad_daily_stats', 'revenue_attributed_cents')) {
                $table->renameColumn('revenue_attributed_cents', 'revenue_attributed');
            }
        });

        // ad_impressions
        Schema::table('ad_impressions', function (Blueprint $table) {
            if (Schema::hasColumn('ad_impressions', 'bid_at_impression_cents')) {
                $table->renameColumn('bid_at_impression_cents', 'bid_at_impression');
            }
            if (Schema::hasColumn('ad_impressions', 'cost_charged_cents')) {
                $table->renameColumn('cost_charged_cents', 'cost_charged');
            }
        });

        // banner_placement_definitions
        Schema::table('banner_placement_definitions', function (Blueprint $table) {
            if (Schema::hasColumn('banner_placement_definitions', 'base_rate_weekly_cents')) {
                $table->renameColumn('base_rate_weekly_cents', 'base_rate_weekly');
            }
        });

        // block_analytics
        Schema::table('block_analytics', function (Blueprint $table) {
            if (Schema::hasColumn('block_analytics', 'revenue_attributed_cents')) {
                $table->renameColumn('revenue_attributed_cents', 'revenue_attributed');
            }
        });

        // country_payment_methods
        Schema::table('country_payment_methods', function (Blueprint $table) {
            if (Schema::hasColumn('country_payment_methods', 'fee_fixed_cents')) {
                $table->renameColumn('fee_fixed_cents', 'fee_fixed');
            }
            if (Schema::hasColumn('country_payment_methods', 'min_order_cents')) {
                $table->renameColumn('min_order_cents', 'min_order');
            }
            if (Schema::hasColumn('country_payment_methods', 'max_order_cents')) {
                $table->renameColumn('max_order_cents', 'max_order');
            }
        });

        // country_shipping_settings
        Schema::table('country_shipping_settings', function (Blueprint $table) {
            if (Schema::hasColumn('country_shipping_settings', 'free_shipping_threshold_cents')) {
                $table->renameColumn('free_shipping_threshold_cents', 'free_shipping_threshold');
            }
        });

        // disputes
        Schema::table('disputes', function (Blueprint $table) {
            if (Schema::hasColumn('disputes', 'compensation_cents')) {
                $table->renameColumn('compensation_cents', 'compensation');
            }
        });

        // paid_ad_bookings
        Schema::table('paid_ad_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('paid_ad_bookings', 'agreed_rate_cents')) {
                $table->renameColumn('agreed_rate_cents', 'agreed_rate');
            }
        });

        // paid_ad_slots
        Schema::table('paid_ad_slots', function (Blueprint $table) {
            if (Schema::hasColumn('paid_ad_slots', 'base_rate_cents')) {
                $table->renameColumn('base_rate_cents', 'base_rate');
            }
        });

        // paid_banner_bookings
        Schema::table('paid_banner_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('paid_banner_bookings', 'rate_cents')) {
                $table->renameColumn('rate_cents', 'rate');
            }
            if (Schema::hasColumn('paid_banner_bookings', 'total_charged_cents')) {
                $table->renameColumn('total_charged_cents', 'total_charged');
            }
        });

        // return_requests
        Schema::table('return_requests', function (Blueprint $table) {
            if (Schema::hasColumn('return_requests', 'refund_amount_cents')) {
                $table->renameColumn('refund_amount_cents', 'refund_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ab_test_results', function (Blueprint $table) {
            if (Schema::hasColumn('ab_test_results', 'revenue')) {
                $table->renameColumn('revenue', 'revenue_cents');
            }
            if (Schema::hasColumn('ab_test_results', 'revenue_per_visitor')) {
                $table->renameColumn('revenue_per_visitor', 'revenue_per_visitor_cents');
            }
        });
        Schema::table('ad_clicks', function (Blueprint $table) {
            if (Schema::hasColumn('ad_clicks', 'cost')) {
                $table->renameColumn('cost', 'cost_cents');
            }
        });
        Schema::table('ad_daily_stats', function (Blueprint $table) {
            if (Schema::hasColumn('ad_daily_stats', 'spend')) {
                $table->renameColumn('spend', 'spend_cents');
            }
            if (Schema::hasColumn('ad_daily_stats', 'revenue_attributed')) {
                $table->renameColumn('revenue_attributed', 'revenue_attributed_cents');
            }
        });
        Schema::table('ad_impressions', function (Blueprint $table) {
            if (Schema::hasColumn('ad_impressions', 'bid_at_impression')) {
                $table->renameColumn('bid_at_impression', 'bid_at_impression_cents');
            }
            if (Schema::hasColumn('ad_impressions', 'cost_charged')) {
                $table->renameColumn('cost_charged', 'cost_charged_cents');
            }
        });
        Schema::table('banner_placement_definitions', function (Blueprint $table) {
            if (Schema::hasColumn('banner_placement_definitions', 'base_rate_weekly')) {
                $table->renameColumn('base_rate_weekly', 'base_rate_weekly_cents');
            }
        });
        Schema::table('block_analytics', function (Blueprint $table) {
            if (Schema::hasColumn('block_analytics', 'revenue_attributed')) {
                $table->renameColumn('revenue_attributed', 'revenue_attributed_cents');
            }
        });
        Schema::table('country_payment_methods', function (Blueprint $table) {
            if (Schema::hasColumn('country_payment_methods', 'fee_fixed')) {
                $table->renameColumn('fee_fixed', 'fee_fixed_cents');
            }
            if (Schema::hasColumn('country_payment_methods', 'min_order')) {
                $table->renameColumn('min_order', 'min_order_cents');
            }
            if (Schema::hasColumn('country_payment_methods', 'max_order')) {
                $table->renameColumn('max_order', 'max_order_cents');
            }
        });
        Schema::table('country_shipping_settings', function (Blueprint $table) {
            if (Schema::hasColumn('country_shipping_settings', 'free_shipping_threshold')) {
                $table->renameColumn('free_shipping_threshold', 'free_shipping_threshold_cents');
            }
        });
        Schema::table('disputes', function (Blueprint $table) {
            if (Schema::hasColumn('disputes', 'compensation')) {
                $table->renameColumn('compensation', 'compensation_cents');
            }
        });
        Schema::table('paid_ad_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('paid_ad_bookings', 'agreed_rate')) {
                $table->renameColumn('agreed_rate', 'agreed_rate_cents');
            }
        });
        Schema::table('paid_ad_slots', function (Blueprint $table) {
            if (Schema::hasColumn('paid_ad_slots', 'base_rate')) {
                $table->renameColumn('base_rate', 'base_rate_cents');
            }
        });
        Schema::table('paid_banner_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('paid_banner_bookings', 'rate')) {
                $table->renameColumn('rate', 'rate_cents');
            }
            if (Schema::hasColumn('paid_banner_bookings', 'total_charged')) {
                $table->renameColumn('total_charged', 'total_charged_cents');
            }
        });
        Schema::table('return_requests', function (Blueprint $table) {
            if (Schema::hasColumn('return_requests', 'refund_amount')) {
                $table->renameColumn('refund_amount', 'refund_amount_cents');
            }
        });
    }
};
