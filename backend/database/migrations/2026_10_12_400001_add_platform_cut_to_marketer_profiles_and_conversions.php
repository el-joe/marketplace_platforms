<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->decimal('platform_cut_percentage', 5, 2)->default(0)->after('commission_discount_notes')
                ->comment('Admin cut (%) taken from every commission this marketer earns.');
        });

        Schema::table('marketer_campaign_conversions', function (Blueprint $table) {
            // Snapshot written once at approval (all BIGINT base units, see MarketerCommissionStatementService).
            $table->bigInteger('gross_commission_amount')->nullable();
            $table->decimal('platform_cut_percentage', 5, 2)->nullable();
            $table->bigInteger('platform_cut_base_amount')->nullable()->comment('cut before the profile discount');
            $table->bigInteger('platform_cut_discount_amount')->nullable();
            $table->bigInteger('platform_cut_amount')->nullable()->comment('cut after discount, before VAT');
            $table->decimal('platform_cut_vat_rate', 5, 2)->nullable();
            $table->bigInteger('platform_cut_vat_amount')->nullable();
            $table->bigInteger('net_commission_amount')->nullable()->comment('credited to the marketer wallet');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_campaign_conversions', function (Blueprint $table) {
            $table->dropColumn(['gross_commission_amount', 'platform_cut_percentage', 'platform_cut_base_amount',
                'platform_cut_discount_amount', 'platform_cut_amount', 'platform_cut_vat_rate',
                'platform_cut_vat_amount', 'net_commission_amount']);
        });
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->dropColumn('platform_cut_percentage');
        });
    }
};
