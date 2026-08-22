<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gift_card_id')->constrained('gift_cards');
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->bigInteger('amount_cents');
            $table->bigInteger('balance_after_cents');
            $table->enum('type', ['issuance', 'redemption', 'refund', 'admin_adjustment', 'expiry']);
            $table->foreignUuid('performed_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('performed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_transactions');
    }
};
