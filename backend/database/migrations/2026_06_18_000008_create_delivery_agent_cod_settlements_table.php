<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_agent_cod_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained('delivery_agents')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('total_cod_collected_cents');
            $table->bigInteger('total_earnings_owed_cents');
            $table->bigInteger('net_to_remit_cents');
            $table->enum('status', ['pending', 'settled', 'disputed'])->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_agent_cod_settlements');
    }
};
