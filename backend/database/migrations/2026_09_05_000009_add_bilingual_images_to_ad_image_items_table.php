<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_image_items', function (Blueprint $table) {
            $table->unsignedBigInteger('file_id_en')->nullable()->after('file_id');
            $table->unsignedBigInteger('file_id_ar')->nullable()->after('file_id_en');

            $table->foreign('file_id_en')->references('id')->on('files')->nullOnDelete();
            $table->foreign('file_id_ar')->references('id')->on('files')->nullOnDelete();
        });

        DB::statement('UPDATE ad_image_items SET file_id_en = file_id, file_id_ar = file_id WHERE file_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('ad_image_items', function (Blueprint $table) {
            $table->dropForeign(['file_id_en']);
            $table->dropForeign(['file_id_ar']);
            $table->dropColumn(['file_id_en', 'file_id_ar']);
        });
    }
};
