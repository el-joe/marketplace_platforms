<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_block_brands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_block_id')
                ->constrained('page_blocks')->cascadeOnDelete();
            $table->foreignUuid('brand_id')
                ->constrained('brands')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_block_brands');
    }
};
