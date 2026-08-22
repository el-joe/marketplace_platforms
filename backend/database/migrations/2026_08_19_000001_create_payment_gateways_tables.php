<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── payment_gateways: global gateway registry ──────────────────────
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            // type: redirect = browser goes to gateway page (Thawani, Paytabs)
            //       direct   = API charge in background (Stripe)
            //       offline  = no API, manual confirmation (bank_transfer, cod)
            //       internal = platform-handled (wallet)
            $table->enum('type', ['redirect', 'direct', 'offline', 'internal']);
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->string('image', 500)->nullable();
            // required_fields: array of {key, label, label_ar, secret, placeholder}
            // 'secret' = true means field is rendered as password input and stored encrypted
            $table->json('required_fields')->nullable();
            $table->boolean('supports_webhook')->default(false);
            $table->boolean('supports_refund')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── country_payment_gateways: per-country configuration ────────────
        Schema::create('country_payment_gateways', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->index();
            $table->uuid('gateway_id')->index();
            $table->string('display_name_en', 100);
            $table->string('display_name_ar', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('environment', ['sandbox', 'production'])->default('sandbox');
            // Stored encrypted via Crypt::encryptString(json_encode([key => value]))
            // Keys must match payment_gateways.required_fields[*].key
            $table->text('credentials_encrypted')->nullable();
            $table->text('webhook_secret_encrypted')->nullable();
            // All monetary values: BIGINT base currency units (matching platform convention)
            $table->decimal('fee_pct', 5, 2)->default(0.00);
            $table->bigInteger('fee_fixed')->default(0);
            $table->bigInteger('min_order')->default(0);
            $table->bigInteger('max_order')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('last_verified_at')->nullable();
            $table->enum('last_verification_status', ['success', 'failed'])->nullable();
            $table->text('last_verification_message')->nullable();
            $table->timestamps();

            $table->unique(['country_id', 'gateway_id']);
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('gateway_id')->references('id')->on('payment_gateways')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_payment_gateways');
        Schema::dropIfExists('payment_gateways');
    }
};
