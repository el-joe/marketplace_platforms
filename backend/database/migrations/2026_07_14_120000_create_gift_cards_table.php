<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('gift_card_batch_id')->nullable();
            $table->foreign('gift_card_batch_id')->references('id')->on('gift_card_batches')->nullOnDelete();

            $table->char('code', 16)->unique();
            $table->string('pin_hash');

            $table->unsignedBigInteger('amount');
            $table->char('currency_code', 3);

            $table->enum('status', ['inactive', 'active', 'redeemed', 'expired'])->default('inactive');

            $table->uuid('redeemed_by_customer_id')->nullable();
            $table->foreign('redeemed_by_customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('currency_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
