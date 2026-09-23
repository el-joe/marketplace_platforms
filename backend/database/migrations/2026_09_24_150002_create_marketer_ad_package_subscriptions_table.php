<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketer_ad_package_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_id');
            $table->uuid('package_id');
            $table->unsignedBigInteger('price')->comment('Snapshot of package price (ex-VAT). BIGINT.');
            $table->unsignedTinyInteger('vat_pct')->comment('Snapshot of VAT percentage');
            $table->unsignedInteger('duration_days')->comment('Snapshot of package duration');
            $table->unsignedBigInteger('amount_paid')->comment('BIGINT = price + vat_amount. No /100.');
            $table->unsignedBigInteger('vat_amount')->comment('BIGINT. No /100.');
            $table->string('currency', 3);
            $table->enum('payment_method', ['wallet', 'bank_transfer', 'online'])->default('wallet');
            $table->string('payment_proof_path')->nullable();
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->foreign('marketer_id')->references('id')->on('marketers')->onDelete('cascade');
            $table->foreign('package_id')->references('id')->on('marketer_ad_packages')->onDelete('restrict');
            $table->index(['marketer_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_ad_package_subscriptions');
    }
};
