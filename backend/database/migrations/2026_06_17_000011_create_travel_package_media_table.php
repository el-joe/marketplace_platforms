<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_package_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_package_id')->constrained('travel_packages')->cascadeOnDelete();
            $table->enum('media_type', ['image', 'video']);
            $table->string('file_path');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_package_media');
    }
};
