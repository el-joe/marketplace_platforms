<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The code the customer types — e.g. NOON-EID-ABCD1234
            $table->string('code', 50)->unique();

            // Admin-facing labels
            $table->string('name', 150);
            $table->text('description')->nullable();

            // Customer-facing display names (bilingual)
            $table->string('title_ar', 255)->nullable();
            $table->string('title_en', 255)->nullable();

            // Monetary value — BIGINT base-currency units, never divide by 100
            $table->unsignedBigInteger('amount')
                  ->comment('BIGINT base-currency. e.g. 5000 = 50 SAR. Never /100.');
            $table->char('currency_code', 3);  // SAR, AED, EGP, KWD, OMR, QAR, BHD, JOD

            // Geographic restriction — NULL = valid in all countries
            $table->char('country_id', 36)->nullable();

            // Who can use it
            $table->enum('customer_eligibility', ['all', 'new_customers', 'specific_users'])
                  ->default('all');
            $table->json('eligible_customer_ids')
                  ->nullable()
                  ->comment('JSON array of customer UUIDs. Used when customer_eligibility = specific_users.');

            // Usage limits
            $table->unsignedInteger('usage_limit_total')
                  ->nullable()
                  ->comment('Max redemptions across all customers. NULL = unlimited.');
            $table->unsignedInteger('usage_limit_per_customer')
                  ->default(1);
            $table->unsignedInteger('times_redeemed')->default(0);

            // Validity window
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->boolean('is_active')->default(true);

            // Audit
            $table->char('created_by_admin_id', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('currency_code');
            $table->index('is_active');
            $table->foreign('country_id')
                  ->references('id')->on('countries')->onDelete('set null');
            $table->foreign('created_by_admin_id')
                  ->references('id')->on('admins')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
