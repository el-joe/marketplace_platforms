<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('portal_contents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('page_key')->index();
            $table->string('block_key');
            $table->string('field_key');
            $table->enum('type', ['text', 'richtext', 'link'])->default('text');
            $table->text('value_en')->nullable();
            $table->text('value_ar')->nullable();
            $table->string('value_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['page_key', 'block_key', 'field_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portal_contents');
    }
};
