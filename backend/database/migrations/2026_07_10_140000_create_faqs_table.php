<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('context')->default('seller')->index();
            $table->string('question_en');
            $table->string('question_ar');
            $table->text('answer_en');
            $table->text('answer_ar');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
