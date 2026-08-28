<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('keyword', 255);
            $table->string('keyword_normalized', 255);
            $table->uuid('country_id');
            $table->unsignedBigInteger('search_count')->default(1);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');

            $table->unique(['keyword_normalized', 'country_id'], 'uq_keyword_country');
            $table->index(['country_id', 'keyword_normalized'], 'idx_keyword_prefix');
            $table->index(['country_id', 'search_count', 'is_blocked'], 'idx_trending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_suggestions');
    }
};
