<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_agent_earnings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->foreignUuid('agent_id')->constrained('delivery_agents');
            $table->foreignUuid('delivery_assignment_id')->constrained('delivery_assignments');
            $table->foreignUuid('order_id')->constrained('orders');
            $table->enum('earning_type', ['base_fee', 'cod_handling', 'bonus', 'tip', 'deduction']);
            $table->bigInteger('amount_cents');
            $table->char('currency', 3);
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_agent_earnings');
    }
};
