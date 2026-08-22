<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_monthly_minimums');
    }
};
