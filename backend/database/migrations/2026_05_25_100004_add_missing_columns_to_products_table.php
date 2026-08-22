<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'requires_brand_auth')) {
                $table->boolean('requires_brand_auth')->default(false)->after('is_featured');
            }
            if (!Schema::hasColumn('products', 'ai_quality_score')) {
                $table->unsignedTinyInteger('ai_quality_score')->nullable()->after('view_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumnIfExists('requires_brand_auth');
            $table->dropColumnIfExists('ai_quality_score');
        });
    }
};
