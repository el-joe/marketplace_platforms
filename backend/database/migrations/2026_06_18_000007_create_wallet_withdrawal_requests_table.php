<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_withdrawal_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->bigInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_iban', 50)->nullable();
            $table->enum('status', ['pending', 'approved', 'processed', 'rejected'])->default('pending');
            $table->uuid('approved_by_admin_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_withdrawal_requests');
    }
};
