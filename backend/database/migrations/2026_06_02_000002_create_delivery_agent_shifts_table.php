<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_agent_shifts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->foreignUuid('agent_id')->constrained('delivery_agents');
            $table->date('shift_date');
            $table->char('zone_id', 36)->nullable()->index();
            $table->foreign('zone_id')->references('id')->on('delivery_zones')->nullOnDelete();
            $table->time('scheduled_start');
            $table->time('scheduled_end');
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->enum('status', ['scheduled', 'active', 'completed', 'no_show', 'cancelled'])->default('scheduled');
            $table->integer('total_deliveries')->default(0);
            $table->bigInteger('total_earnings_cents')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_agent_shifts');
    }
};
