<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('influencer_monthly_stats');
    }
};
