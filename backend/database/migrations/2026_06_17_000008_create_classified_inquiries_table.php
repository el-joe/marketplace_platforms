<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classified_inquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('classified_listing_id');
            $table->uuid('customer_id');
            $table->text('message')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->enum('status', ['new', 'contacted', 'closed'])->default('new');
            $table->timestamps();

            $table->foreign('classified_listing_id')->references('id')->on('classified_listings')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classified_inquiries');
    }
};
