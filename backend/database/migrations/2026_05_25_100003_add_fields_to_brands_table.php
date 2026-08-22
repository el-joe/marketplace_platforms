<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('logo_media_id')->nullable()->after('slug');
            $table->boolean('is_restricted')->default(false)->after('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['logo_media_id', 'is_restricted']);
        });
    }
};
