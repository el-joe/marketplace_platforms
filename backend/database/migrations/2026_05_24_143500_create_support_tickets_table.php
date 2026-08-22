<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number', 30)->unique();
            $table->uuid('requester_user_id')->index();
            $table->enum('requester_role', ['customer', 'seller']);
            $table->enum('category', [
                'order_issue',
                'payment_issue',
                'account',
                'technical',
                'product_inquiry',
                'policy',
                'payout',
                'catalog',
                'other',
            ]);
            $table->enum('priority', ['low', 'normal', 'high', 'urgent']);
            $table->enum('status', [
                'open',
                'in_progress',
                'waiting_customer',
                'resolved',
                'closed',
            ]);
            $table->string('subject', 255);
            $table->text('description');
            $table->uuid('assigned_to_admin_id')->nullable()->index();
            $table->uuid('related_order_id')->nullable()->index();
            $table->uuid('related_product_id')->nullable()->index();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->tinyInteger('satisfaction_rating')->nullable();
            $table->text('satisfaction_comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
