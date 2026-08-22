<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_context_countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('app_context_id');
            $table->uuid('country_id');
            $table->uuid('home_page_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['app_context_id', 'country_id']);

            $table->foreign('app_context_id')->references('id')->on('app_contexts')->onDelete('cascade');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('home_page_id')->references('id')->on('pages')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_context_countries');
    }
};
