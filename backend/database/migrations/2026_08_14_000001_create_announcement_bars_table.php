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
        Schema::create('announcement_bars', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->index()->nullable();
            $table->string('name', 150);
            $table->string('message_en', 500);
            $table->string('message_ar', 500);
            $table->string('cta_label_en', 100)->nullable();
            $table->string('cta_label_ar', 100)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('bg_color_hex', 7)->nullable();
            $table->string('text_color_hex', 7)->nullable();
            $table->boolean('is_dismissible')->default(true);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->integer('priority')->default(0);
            $table->uuid('created_by_admin_id')->index();
            $table->uuid('updated_by_admin_id')->index()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_bars');
    }
};
