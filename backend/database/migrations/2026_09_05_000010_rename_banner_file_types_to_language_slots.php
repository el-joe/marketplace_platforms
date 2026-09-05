<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('files')->where('file_type', 'banner_desktop')->update(['file_type' => 'banner_desktop_en']);
        DB::table('files')->where('file_type', 'banner_mobile')->update(['file_type' => 'banner_mobile_en']);
    }

    public function down(): void
    {
        DB::table('files')->where('file_type', 'banner_desktop_en')->update(['file_type' => 'banner_desktop']);
        DB::table('files')->where('file_type', 'banner_mobile_en')->update(['file_type' => 'banner_mobile']);
    }
};
