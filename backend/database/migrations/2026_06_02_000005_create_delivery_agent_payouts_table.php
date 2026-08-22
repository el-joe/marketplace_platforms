<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_agent_payouts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('payout_number', 30)->unique();
            $table->foreignUuid('agent_id')->constrained('delivery_agents');
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_deliveries')->default(0);
            $table->bigInteger('gross_earnings_cents')->default(0);
            $table->bigInteger('deductions_cents')->default(0);
            $table->bigInteger('net_amount_cents')->default(0);
            $table->char('currency', 3);
            $table->enum('status', ['pending', 'approved', 'paid', 'failed'])->default('pending');
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->char('approved_by_admin_id', 36)->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_agent_payouts');
    }
};
