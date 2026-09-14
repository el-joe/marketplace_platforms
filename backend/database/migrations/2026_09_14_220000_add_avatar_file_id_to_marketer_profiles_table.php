<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->foreignId('avatar_file_id')
                ->nullable()
                ->after('banner_file_id')
                ->constrained('files')
                ->nullOnDelete()
                ->comment('Profile/avatar image, distinct from the cover banner_file_id.');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avatar_file_id');
        });
    }
};
