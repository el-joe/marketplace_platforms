<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketer_campaign_conversions', function (Blueprint $table) {
            $table->foreignUuid('flash_sale_id')->nullable()->after('tiered_rule_id')
                ->constrained('flash_sales')->nullOnDelete();
            $table->bigInteger('flash_sale_bonus_amount')->nullable()->after('flash_sale_id')
                ->comment('Base-currency integer bonus commission earned during a flash sale window, on top of commission_amount.');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_campaign_conversions', function (Blueprint $table) {
            $table->dropForeign(['flash_sale_id']);
            $table->dropColumn(['flash_sale_id', 'flash_sale_bonus_amount']);
        });
    }
};
