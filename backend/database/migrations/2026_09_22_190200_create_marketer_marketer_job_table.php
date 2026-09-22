<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_marketer_job', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('marketer_id')->constrained('marketers')->cascadeOnDelete();
            $table->foreignUuid('marketer_job_id')->constrained('marketer_jobs')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['marketer_id', 'marketer_job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_marketer_job');
    }
};
