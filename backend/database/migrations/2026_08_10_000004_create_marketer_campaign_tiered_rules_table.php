<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketer_campaign_tiered_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('marketer_campaigns')->cascadeOnDelete();

            // Milestone: when marketer reaches this many total sales in this campaign
            $table->unsignedInteger('from_sale_number')
                  ->comment('Commission tier activates when marketer reaches this sale number. e.g. 10, 35, 75, 120');

            // Commission amount at this tier
            $table->bigInteger('commission_amount')
                  ->comment('Commission per sale at this tier. BIGINT base-currency. No /100.');

            $table->char('currency', 3);
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['campaign_id', 'from_sale_number'], 'mctr_campaign_sale_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_campaign_tiered_rules');
    }
};
