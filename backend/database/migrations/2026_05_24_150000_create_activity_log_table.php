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
        Schema::create('activity_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('log_name', 100);
            $table->string('description', 255);
            $table->string('subject_type', 255)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('causer_type', 255)->nullable();
            $table->uuid('causer_id')->nullable();
            $table->json('properties');
            $table->string('event', 50)->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['causer_type', 'causer_id']);
            $table->index(['log_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
