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
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 150)->unique();
            $table->json('value');
            $table->string('category', 50);
            $table->text('description')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_public')->default(false);
            $table->uuid('updated_by_admin_id')->nullable();
            $table->timestamp('updated_at')->nullable();

            // $table->foreign('updated_by_admin_id')->references('id')->on('users')->nullOnDelete();

            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
