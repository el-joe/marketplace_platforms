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
        Schema::create('marketer_campaign_category_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_campaign_id')
                ->comment('The campaign this include/exclude rule belongs to.');
            $table->uuid('category_id')->nullable()
                ->comment('Product Category — set when this rule is a product-category rule (mutually exclusive with classified_category_id).');
            $table->uuid('classified_category_id')->nullable()
                ->comment('Open-market ClassifiedCategory — set when this rule is a classified-category rule (mutually exclusive with category_id).');
            $table->string('mode', 20)
                ->comment('include | exclude — must match the owning campaign\'s corresponding *_category_selection_mode.');
            $table->timestamps();

            $table->foreign('marketer_campaign_id')->references('id')->on('marketer_campaigns')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('classified_category_id')->references('id')->on('classified_categories')->cascadeOnDelete();

            $table->index(['marketer_campaign_id', 'category_id'], 'mccr_campaign_category_idx');
            $table->index(['marketer_campaign_id', 'classified_category_id'], 'mccr_campaign_classified_category_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketer_campaign_category_rules');
    }
};
