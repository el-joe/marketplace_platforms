<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packaging_supplies', function (Blueprint $table) {
            if (!Schema::hasColumn('packaging_supplies', 'description_en')) {
                $table->text('description_en')->nullable()->after('name_ar');
            }
            if (!Schema::hasColumn('packaging_supplies', 'description_ar')) {
                $table->text('description_ar')->nullable()->after('description_en');
            }
            if (!Schema::hasColumn('packaging_supplies', 'sort_order')) {
                $table->smallInteger('sort_order')->default(0)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packaging_supplies', function (Blueprint $table) {
            if (Schema::hasColumn('packaging_supplies', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
            if (Schema::hasColumn('packaging_supplies', 'description_ar')) {
                $table->dropColumn('description_ar');
            }
            if (Schema::hasColumn('packaging_supplies', 'description_en')) {
                $table->dropColumn('description_en');
            }
        });
    }
};
