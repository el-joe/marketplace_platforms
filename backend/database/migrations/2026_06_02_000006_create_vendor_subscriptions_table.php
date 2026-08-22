<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name_en', 150);
            $table->string('name_ar', 150);
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->bigInteger('price_cents');
            $table->char('currency', 3);
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'annual'])->default('monthly');
            $table->integer('max_listings')->nullable()->comment('NULL = unlimited');
            $table->tinyInteger('free_shipping_included')->default(0);
            $table->decimal('commission_discount_pct', 5, 2)->default(0.00)->comment('Reduces standard commission by this %');
            $table->json('features')->nullable()->comment('Array of feature strings');
            $table->tinyInteger('is_active')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('vendor_subscriptions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors');
            $table->foreignUuid('plan_id')->constrained('subscription_plans');
            $table->enum('status', ['active', 'cancelled', 'expired', 'past_due', 'trialing'])->default('active');
            $table->timestamp('started_at');
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->tinyInteger('auto_renew')->default(1);
            $table->integer('listings_used')->default(0);
            $table->char('approved_by_admin_id', 36)->nullable()->index();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        Schema::create('vendor_subscription_invoices', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors');
            $table->char('subscription_id', 36)->index();
            $table->foreign('subscription_id')->references('id')->on('vendor_subscriptions')->cascadeOnDelete();
            $table->string('invoice_number', 30)->unique();
            $table->bigInteger('amount_cents');
            $table->char('currency', 3);
            $table->enum('status', ['draft', 'open', 'paid', 'void', 'uncollectible'])->default('open');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('paid_at')->nullable();
            $table->char('payment_transaction_id', 36)->nullable()->index();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_subscription_invoices');
        Schema::dropIfExists('vendor_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
