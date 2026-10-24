<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_marketer', function (Blueprint $table) {
            $table->foreignUuid('banner_id')->constrained('banners')->cascadeOnDelete();
            $table->foreignUuid('marketer_id')->constrained('marketers')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['banner_id', 'marketer_id']);
            $table->index('marketer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_marketer');
    }
};
