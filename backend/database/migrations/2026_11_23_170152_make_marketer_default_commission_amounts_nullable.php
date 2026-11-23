<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WS12 #25: per-category commission settings are edited on the classified category page. A category row must be
 * able to override ONE value (e.g. only the commission fee) and inherit the others, so NULL now means "inherit"
 * (parent category, then the country x vertical default) and 0 means an explicit zero.
 *
 * Category rows only ever got their fee columns from the column default (0/0), which the resolver already treated
 * as "not set"; they become NULL so their meaning does not change. Vertical-wide rows are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_default_commissions', function (Blueprint $table) {
            $table->decimal('influencer_fixed_amount', 19, 4)->nullable()->default(null)->comment('Fixed amount per campaign; NULL = inherit')->change();
            $table->decimal('commission_fee_percentage', 7, 4)->nullable()->default(null)->comment('Platform fee % of the marketer commission; NULL = inherit')->change();
            $table->decimal('commission_fee_fixed', 19, 4)->nullable()->default(null)->comment('Platform fixed fee per commission; NULL = inherit')->change();
        });

        DB::table('marketer_default_commissions')
            ->whereNotNull('category_id')
            ->where('commission_fee_percentage', 0)
            ->where('commission_fee_fixed', 0)
            ->update(['commission_fee_percentage' => null, 'commission_fee_fixed' => null]);
    }

    public function down(): void
    {
        DB::table('marketer_default_commissions')->whereNull('influencer_fixed_amount')->update(['influencer_fixed_amount' => 0]);
        DB::table('marketer_default_commissions')->whereNull('commission_fee_percentage')->update(['commission_fee_percentage' => 0]);
        DB::table('marketer_default_commissions')->whereNull('commission_fee_fixed')->update(['commission_fee_fixed' => 0]);

        Schema::table('marketer_default_commissions', function (Blueprint $table) {
            $table->decimal('influencer_fixed_amount', 19, 4)->default(0)->comment('Fixed amount per campaign')->change();
            $table->decimal('commission_fee_percentage', 7, 4)->default(0)->comment('Platform fee % of the marketer commission')->change();
            $table->decimal('commission_fee_fixed', 19, 4)->default(0)->comment('Platform fixed fee per commission')->change();
        });
    }
};
