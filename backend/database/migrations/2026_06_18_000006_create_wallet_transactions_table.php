<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->bigInteger('amount_cents');
            $table->bigInteger('balance_after_cents');
            $table->string('source_type', 50);
            $table->uuid('source_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->uuid('performed_by_admin_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
