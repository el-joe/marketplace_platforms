<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_unit_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bookable_unit_id')->constrained('bookable_units')->cascadeOnDelete();
            $table->string('file_path');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_unit_photos');
    }
};
