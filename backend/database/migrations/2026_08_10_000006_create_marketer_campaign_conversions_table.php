<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketer_campaign_conversions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('marketer_campaigns')->cascadeOnDelete();
            $table->foreignUuid('invitation_id')->constrained('marketer_campaign_invitations')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();

            // For last-click: when was the referral link clicked
            $table->timestamp('referral_clicked_at')->nullable();

            // Commission details
            $table->bigInteger('commission_amount')->default(0)
                  ->comment('Commission earned for this conversion. BIGINT base-currency. No /100.');
            $table->char('currency', 3);
            $table->boolean('commissioned')->default(false)
                  ->comment('Has this conversion been paid out?');
            $table->timestamp('paid_at')->nullable();

            // Tiered: which tier applied
            $table->unsignedInteger('sale_number_in_campaign')->nullable()
                  ->comment('Marketer sale sequence number in this campaign — used for tiered commission');
            $table->foreignUuid('tiered_rule_id')->nullable()
                  ->constrained('marketer_campaign_tiered_rules')->nullOnDelete();

            $table->timestamps();
            $table->index(['campaign_id', 'invitation_id']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_campaign_conversions');
    }
};
