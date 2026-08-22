<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('return_number', 30)->unique();
            $table->uuid('order_id')->index();
            $table->uuid('sub_order_id')->index();
            $table->uuid('customer_id')->index();
            $table->uuid('vendor_id')->index();
            $table->enum('reason', [
                'changed_mind',
                'wrong_item',
                'defective',
                'damaged',
                'not_as_described',
                'size_issue',
                'quality_issue',
                'arrived_late',
                'other',
            ]);
            $table->text('reason_description')->nullable();
            $table->enum('return_type', ['refund', 'exchange', 'store_credit']);
            $table->enum('status', [
                'requested',
                'approved',
                'rejected',
                'awaiting_pickup',
                'in_transit',
                'received',
                'inspecting',
                'completed',
                'cancelled',
            ]);
            $table->uuid('pickup_address_id')->nullable()->index();
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->timestamp('received_at_warehouse_at')->nullable();
            $table->enum('inspection_result', ['good', 'damaged', 'missing', 'counterfeit'])->nullable();
            $table->text('inspection_notes')->nullable();
            $table->enum('liability', ['customer', 'seller', 'platform', 'carrier'])->nullable();
            $table->bigInteger('refund_amount_cents')->nullable();
            $table->uuid('refund_id')->nullable()->index();
            $table->text('rejection_reason')->nullable();
            $table->uuid('reviewed_by_admin_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
