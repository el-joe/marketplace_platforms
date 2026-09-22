<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_job_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('marketer_job_id')->constrained('marketer_jobs')->cascadeOnDelete();
            $table->string('category_type'); // 'product' | 'classified' | 'travel'
            $table->timestamps();

            $table->unique(['marketer_job_id', 'category_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_job_categories');
    }
};
