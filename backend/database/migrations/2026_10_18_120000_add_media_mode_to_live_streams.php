<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('live_streams', 'media_mode')) {
            Schema::table('live_streams', function (Blueprint $table) {
                $table->string('media_mode', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('live_streams', 'media_mode')) {
            Schema::table('live_streams', function (Blueprint $table) {
                $table->dropColumn('media_mode');
            });
        }
    }
};
