<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('influencer_monthly_stats');
        Schema::dropIfExists('influencer_monthly_minimums');
    }

    public function down(): void
    {
        Schema::create('influencer_monthly_minimums', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Tier 1, 2, 3, or 4
            $table->unsignedTinyInteger('tier')
                  ->comment('1=Vendor Requests, 2=Open Market, 3=Admin Curated, 4=Nawy Now');

            // Minimum number of promotions per month for this tier
            $table->unsignedSmallInteger('monthly_minimum')
                  ->comment('e.g. 3 for Tier 1, 4 for Tier 2');

            // Penalty per unmet promotion (deducted from celebrity earnings)
            // BIGINT base-currency — NO /100
            $table->unsignedBigInteger('penalty_per_unmet_promotion')
                  ->default(0)
                  ->comment('BIGINT base-currency. Amount deducted per promotion below minimum.');
            $table->char('penalty_currency', 3)->default('SAR');

            $table->boolean('is_active')->default(true);
            $table->char('set_by_admin_id', 36)->nullable();
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamps();

            // Only one active minimum per tier at a time
            $table->unique(['tier', 'is_active']);

            $table->foreign('set_by_admin_id')->references('id')->on('admins')->onDelete('set null');
        });

        Schema::create('influencer_monthly_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->char('marketer_id', 36)->index();
            $table->unsignedTinyInteger('tier')
                  ->comment('1=Vendor Requests, 2=Open Market, 3=Admin Curated, 4=Nawy Now');
            $table->unsignedTinyInteger('month');   // 1–12
            $table->unsignedSmallInteger('year');

            $table->unsignedSmallInteger('promotions_completed')->default(0)
                  ->comment('How many promotions fulfilled this month in this tier.');
            $table->unsignedSmallInteger('monthly_minimum_snapshot')->default(0)
                  ->comment('The minimum that was required this month (snapshot at month start).');

            // Alert flags
            $table->boolean('below_minimum_alert_sent')->default(false);
            $table->timestamp('below_minimum_alert_sent_at')->nullable();

            // Penalty
            $table->boolean('penalty_applied')->default(false);
            $table->unsignedBigInteger('penalty_amount')->default(0)
                  ->comment('BIGINT base-currency penalty applied for this month. 0 if met minimum.');
            $table->timestamp('penalty_applied_at')->nullable();

            $table->timestamps();

            $table->unique(['marketer_id', 'tier', 'month', 'year']);
        });
    }
};
