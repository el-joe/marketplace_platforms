<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_performance_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipping_company_id')->nullable()->constrained('shipping_companies')->nullOnDelete();
            $table->foreignUuid('delivery_agent_id')->nullable()->constrained('delivery_agents')->nullOnDelete();
            $table->foreignUuid('sub_order_id')->constrained('sub_orders');
            $table->enum('rated_by_type', ['customer', 'vendor']);
            $table->uuid('rated_by_id');
            $table->unsignedTinyInteger('rating'); // 1–5 enforced in app
            $table->boolean('on_time')->nullable();
            $table->text('comment')->nullable();
            // Always 0 — carrier identity must never surface to customers
            $table->boolean('visible_to_customer')->default(false);
            $table->timestamps();

            $table->index(['shipping_company_id', 'created_at']);
            $table->index(['delivery_agent_id', 'created_at']);
            $table->index(['sub_order_id', 'rated_by_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_performance_ratings');
    }
};
