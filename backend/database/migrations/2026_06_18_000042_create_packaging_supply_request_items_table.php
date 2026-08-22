<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging_supply_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('packaging_supply_requests')->cascadeOnDelete();
            $table->foreignUuid('packaging_supply_id')->constrained('packaging_supplies')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost_cents');
            $table->unsignedBigInteger('line_total_cents');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging_supply_request_items');
    }
};
