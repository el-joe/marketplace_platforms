<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketer_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_id');
            $table->uuid('customer_id');
            $table->uuid('classified_listing_id')->nullable();
            $table->uuid('classified_inquiry_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('marketer_has_unread')->default(false);
            $table->boolean('customer_has_unread')->default(false);
            $table->timestamps();
            $table->unique(['marketer_id', 'customer_id', 'classified_listing_id'], 'mc_unique_ctx');
            $table->foreign('marketer_id')->references('id')->on('marketers')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('classified_listing_id')->references('id')->on('classified_listings')->onDelete('set null');
            $table->index(['marketer_id', 'last_message_at']);
            $table->index(['customer_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_conversations');
    }
};
