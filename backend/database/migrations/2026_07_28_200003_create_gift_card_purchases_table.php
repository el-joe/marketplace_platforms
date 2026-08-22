<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->char('gift_card_id', 36)->unique()
                  ->comment('One purchase per gift card — 1:1 relationship.');
            $table->char('gift_card_batch_id', 36)->index();
            $table->char('order_id', 36)->index()
                  ->comment('The order placed to buy this gift card.');
            $table->char('buyer_customer_id', 36)->index();

            // Monetary — BIGINT base-currency, NO /100
            $table->unsignedBigInteger('amount_paid')
                  ->comment('What the customer paid. BIGINT base-currency — no /100.');
            $table->char('currency_code', 3);

            // Gifting
            $table->boolean('is_gift')->default(false)
                  ->comment('True = buyer entered a different recipient email.');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name', 150)->nullable();
            $table->text('gift_message')->nullable();

            // Delivery
            $table->enum('delivery_status', ['pending', 'sent', 'failed'])
                  ->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedTinyInteger('delivery_attempts')->default(0);

            $table->timestamps();

            $table->foreign('gift_card_id')
                  ->references('id')->on('gift_cards')->onDelete('restrict');
            $table->foreign('gift_card_batch_id')
                  ->references('id')->on('gift_card_batches')->onDelete('restrict');
            $table->foreign('order_id')
                  ->references('id')->on('orders')->onDelete('restrict');
            $table->foreign('buyer_customer_id')
                  ->references('id')->on('customers')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_purchases');
    }
};
