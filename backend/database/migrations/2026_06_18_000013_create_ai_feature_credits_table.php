<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_feature_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('owner_type', ['vendor', 'marketer']);
            $table->uuid('owner_id');
            $table->string('feature', 50)->comment("'image_enhancement', 'virtual_tryon', 'video_generation'");
            $table->integer('credits_remaining')->default(0);
            $table->integer('credits_used_total')->default(0);
            $table->date('reset_at')->nullable()->comment('Monthly reset date if plan-based');
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'feature'], 'unique_owner_feature');
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feature_credits');
    }
};
