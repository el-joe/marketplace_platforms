<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('review_vendor_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('review_id')->unique();
            $table->uuid('vendor_id');
            $table->text('body');
            $table->enum('status', ['published', 'hidden']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_vendor_replies');
    }
};
