<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_support_collections', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->string('name_ar')->nullable()->after('name_en');
            $table->text('description_en')->nullable()->after('description');
            $table->text('description_ar')->nullable()->after('description_en');
        });

        DB::table('ad_support_collections')->update([
            'name_en' => DB::raw('name'),
            'name_ar' => DB::raw('name'),
            'description_en' => DB::raw('description'),
            'description_ar' => DB::raw('description'),
        ]);
    }

    public function down(): void
    {
        Schema::table('ad_support_collections', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_ar', 'description_en', 'description_ar']);
        });
    }
};
