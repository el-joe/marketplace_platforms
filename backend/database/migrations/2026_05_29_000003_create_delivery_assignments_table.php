<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            $table->foreignUuid('shipment_id')->constrained('shipments');
            $table->foreignUuid('sub_order_id')->constrained('sub_orders');
            $table->foreignUuid('agent_id')->constrained('delivery_agents');

            $table->enum('status', [
                'assigned',
                'accepted',
                'picked_up',
                'delivered',
                'failed',
            ])->default('assigned');

            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->enum('failure_reason', [
                'customer_not_home',
                'wrong_address',
                'customer_refused',
                'damaged_package',
                'other',
            ])->nullable();
            $table->text('failure_notes')->nullable();

            $table->string('delivery_otp', 10)->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->boolean('otp_verified')->default(false);

            // proof_file_id → files.id (files.id is bigint in this codebase)
            $table->unsignedBigInteger('proof_file_id')->nullable();
            $table->foreign('proof_file_id')->references('id')->on('files')->nullOnDelete();

            $table->text('agent_notes')->nullable();

            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();

            $table->unsignedTinyInteger('customer_rating')->nullable();

            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['status', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_assignments');
    }
};
