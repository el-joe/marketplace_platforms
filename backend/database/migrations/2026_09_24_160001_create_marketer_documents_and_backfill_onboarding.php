<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('marketer_id')->constrained('marketers')->cascadeOnDelete();
            $table->string('type', 30); // cv | certification
            $table->string('disk', 30)->default('private');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['marketer_id', 'type']);
        });

        // Existing marketers must not be locked out by the new onboarding gate.
        DB::table('marketers')
            ->whereNull('onboarding_completed_at')
            ->update(['onboarding_completed_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_documents');
    }
};
