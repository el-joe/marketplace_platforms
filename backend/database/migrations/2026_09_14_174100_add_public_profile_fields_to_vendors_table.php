<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->text('store_description_ar')->nullable()->after('store_description');
            $table->string('specialization_en')->nullable()->after('store_description_ar');
            $table->string('specialization_ar')->nullable()->after('specialization_en');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['store_description_ar', 'specialization_en', 'specialization_ar']);
        });
    }
};
