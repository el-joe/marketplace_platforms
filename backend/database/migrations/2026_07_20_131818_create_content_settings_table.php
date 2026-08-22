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
        Schema::create('content_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('group');
            $table->string('section');
            $table->string('key')->unique();
            $table->string('type');
            $table->longText('value')->nullable();
            $table->json('options')->nullable();
            $table->string('default_value')->nullable();
            $table->string('allowed_extensions')->nullable();
            $table->unsignedInteger('max_size_kb')->nullable();
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['group', 'section']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_settings');
    }
};
