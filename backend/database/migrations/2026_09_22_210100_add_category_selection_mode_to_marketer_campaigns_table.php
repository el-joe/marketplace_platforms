<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->string('product_category_selection_mode', 20)->default('all')
                ->comment('all | include | exclude — scopes which Category rows this campaign/marketer setting applies to. Specific ids live in marketer_campaign_category_rules.');
            $table->string('classified_category_selection_mode', 20)->default('all')
                ->comment('all | include | exclude — scopes which ClassifiedCategory rows this campaign/marketer setting applies to. Specific ids live in marketer_campaign_category_rules.')
                ->after('product_category_selection_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->dropColumn(['product_category_selection_mode', 'classified_category_selection_mode']);
        });
    }
};
