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
        Schema::create('footer_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('group', ['social', 'bottom_nav', 'app_store', 'payment_method']);
            $table->string('platform')->nullable();
            $table->string('label_en')->nullable();
            $table->string('label_ar')->nullable();
            $table->string('url')->nullable();
            $table->string('icon_path')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['group', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('footer_links');
    }
};
