<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // High-write table — purge entries older than 90 days in a scheduled job.
        // Consider RANGE partitioning by recorded_at in production.
        Schema::create('agent_location_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('agent_id', 36);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('recorded_at')->useCurrent();

            $table->foreign('agent_id')->references('id')->on('delivery_agents')->cascadeOnDelete();
            $table->index(['agent_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_location_history');
    }
};
