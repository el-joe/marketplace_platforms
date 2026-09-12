<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->foreignUuid('broker_category_id')
                  ->nullable()
                  ->after('measurements_notes')
                  ->constrained('categories')
                  ->nullOnDelete()
                  ->comment('The category this broker specializes in (e.g. Real Estate, Cars). Affiliate marketers only.');

            $table->foreignUuid('broker_city_id')
                  ->nullable()
                  ->after('broker_category_id')
                  ->constrained('cities')
                  ->nullOnDelete()
                  ->comment('The city this broker serves; NULL = all cities (see broker_serves_all_cities)');

            $table->boolean('broker_serves_all_cities')
                  ->default(false)
                  ->after('broker_city_id')
                  ->comment('If true, broker_city_id is ignored and broker serves all cities');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('broker_category_id');
            $table->dropConstrainedForeignId('broker_city_id');
            $table->dropColumn('broker_serves_all_cities');
        });
    }
};
