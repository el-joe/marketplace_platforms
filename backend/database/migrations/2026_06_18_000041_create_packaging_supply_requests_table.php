<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging_supply_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number', 30)->unique();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'shipped', 'delivered', 'rejected'])->default('pending');
            $table->unsignedBigInteger('total_cost_cents')->default(0);
            $table->text('notes')->nullable();
            $table->foreignUuid('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging_supply_requests');
    }
};
