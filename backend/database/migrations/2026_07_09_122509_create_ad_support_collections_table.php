<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_support_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()
                ->comment('Supports one level of sub-collections — a collection with a parent cannot itself be a parent (enforce at app layer, not DB constraint, for simplicity)');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable()
                ->comment('Icon URL shown on the collection card, e.g. an intercom.help svg icon link');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
            $table->foreign('parent_id')->references('id')->on('ad_support_collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_support_collections');
    }
};
