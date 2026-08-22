<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('category_id', 36);
            $table->string('name_en', 150);
            $table->string('name_ar', 150);
            $table->tinyInteger('duration_months')->unsigned()
                ->comment('Coverage duration in months; applied after brand warranty ends');
            $table->json('features_en')->nullable();
            $table->json('features_ar')->nullable();
            $table->json('country_ids')->nullable();
            $table->bigInteger('price_cents');
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->unsigned()->default(0);
            $table->char('created_by_admin_id', 36);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
            $table->foreign('created_by_admin_id')->references('id')->on('admins')->onDelete('restrict');
        });

        Schema::create('warranty_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('customer_id', 36);
            $table->char('order_id', 36);
            $table->char('order_item_id', 36)->unique();
            $table->char('warranty_plan_id', 36);
            $table->json('plan_snapshot')->comment('Immutable copy of plan at purchase time');
            $table->bigInteger('price_paid_cents');
            $table->char('currency', 3);
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
            $table->date('coverage_starts_at')->nullable();
            $table->date('coverage_ends_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['order_id']);

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('restrict');
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('restrict');
            $table->foreign('warranty_plan_id')->references('id')->on('warranty_plans')->onDelete('restrict');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->bigInteger('warranty_total')->default(0)->after('cod_fee');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->char('warranty_purchase_id', 36)->nullable()->after('return_eligible_until');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('warranty_purchase_id')->references('id')->on('warranty_purchases')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['warranty_purchase_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('warranty_purchase_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('warranty_total');
        });

        Schema::dropIfExists('warranty_purchases');
        Schema::dropIfExists('warranty_plans');
    }
};
