<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('dispute_number', 30)->unique();
            $table->uuid('order_id')->index();
            $table->uuid('sub_order_id')->index();
            $table->uuid('customer_id')->index();
            $table->uuid('vendor_id')->index();
            $table->uuid('return_request_id')->nullable()->index();
            $table->enum('reason', [
                'item_not_received',
                'item_damaged',
                'item_not_as_described',
                'counterfeit',
                'wrong_item',
                'quality_issue',
                'seller_unresponsive',
                'refund_not_received',
                'other',
            ]);
            $table->text('description');
            $table->enum('status', [
                'open',
                'seller_responded',
                'under_review',
                'escalated',
                'resolved',
                'closed',
            ]);
            $table->enum('resolution', [
                'favor_customer',
                'favor_seller',
                'split',
                'no_action',
            ])->nullable();
            $table->text('resolution_notes')->nullable();
            $table->bigInteger('compensation_cents')->nullable();
            $table->uuid('assigned_to_admin_id')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
