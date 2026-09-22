<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_marketer_job_category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('marketer_marketer_job_id')->constrained('marketer_marketer_job')->cascadeOnDelete();
            $table->string('category_type'); // 'product' | 'classified'
            $table->uuid('category_id'); // polymorphic target: Category.id or ClassifiedCategory.id, no FK (two possible tables)
            $table->timestamps();

            $table->unique(['marketer_marketer_job_id', 'category_type', 'category_id'], 'mmjc_unique');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_marketer_job_category');
    }
};
