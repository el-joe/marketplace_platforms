<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R2): platform defaults for marketer commissions per country x vertical (product | classified | travel),
 * optionally narrowed to one category of that vertical.
 *
 *  - influencer_fixed_amount: the one fixed amount an influencer earns per campaign (not per sale).
 *  - commission_fee_percentage / commission_fee_fixed: the platform's fee taken from the marketer's commission
 *    (fee = pct x commission + fixed, never more than the commission).
 *
 * `category_id` points at categories / classified_categories / travel_categories depending on `category_type`,
 * so it carries no foreign key. NULLs are distinct in a composite unique index, so uniqueness for the
 * "whole vertical" row (category_id NULL) is enforced through the deterministic `rule_key` (same pattern as
 * marketer_commission_rules).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketer_default_commissions')) {
            return;
        }

        Schema::create('marketer_default_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rule_key', 40);
            $table->foreignUuid('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('category_type', 20)->comment('product | classified | travel');
            $table->char('category_id', 36)->nullable()->comment('NULL = default for the whole vertical');
            $table->decimal('influencer_fixed_amount', 19, 4)->default(0)->comment('Fixed amount per campaign');
            $table->decimal('commission_fee_percentage', 7, 4)->default(0)->comment('Platform fee % of the marketer commission');
            $table->decimal('commission_fee_fixed', 19, 4)->default(0)->comment('Platform fixed fee per commission');
            $table->foreignUuid('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique('rule_key', 'mdc_rule_key_unique');
            $table->unique(['country_id', 'category_type', 'category_id'], 'mdc_country_type_category_unique');
            $table->index(['category_type', 'category_id'], 'mdc_type_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_default_commissions');
    }
};
