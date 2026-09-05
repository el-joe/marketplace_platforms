<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slider_slides', function (Blueprint $table) {
            $table->unsignedBigInteger('desktop_file_id_en')->nullable()->after('desktop_file_id');
            $table->unsignedBigInteger('desktop_file_id_ar')->nullable()->after('desktop_file_id_en');
            $table->unsignedBigInteger('mobile_file_id_en')->nullable()->after('mobile_file_id');
            $table->unsignedBigInteger('mobile_file_id_ar')->nullable()->after('mobile_file_id_en');

            $table->foreign('desktop_file_id_en')->references('id')->on('files')->nullOnDelete();
            $table->foreign('desktop_file_id_ar')->references('id')->on('files')->nullOnDelete();
            $table->foreign('mobile_file_id_en')->references('id')->on('files')->nullOnDelete();
            $table->foreign('mobile_file_id_ar')->references('id')->on('files')->nullOnDelete();
        });

        DB::statement('UPDATE slider_slides SET desktop_file_id_en = desktop_file_id, desktop_file_id_ar = desktop_file_id WHERE desktop_file_id IS NOT NULL');
        DB::statement('UPDATE slider_slides SET mobile_file_id_en = mobile_file_id, mobile_file_id_ar = mobile_file_id WHERE mobile_file_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('slider_slides', function (Blueprint $table) {
            $table->dropForeign(['desktop_file_id_en']);
            $table->dropForeign(['desktop_file_id_ar']);
            $table->dropForeign(['mobile_file_id_en']);
            $table->dropForeign(['mobile_file_id_ar']);
            $table->dropColumn(['desktop_file_id_en', 'desktop_file_id_ar', 'mobile_file_id_en', 'mobile_file_id_ar']);
        });
    }
};
