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
        Schema::create('review_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('review_id');
            $table->uuid('customer_id');
            $table->enum('vote', ['helpful', 'not_helpful']);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_votes');
    }
};
