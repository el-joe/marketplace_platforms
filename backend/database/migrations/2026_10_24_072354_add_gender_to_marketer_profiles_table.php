<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female'])->nullable()->after('profile_slug');
            $table->index('gender');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->dropIndex(['gender']);
            $table->dropColumn('gender');
        });
    }
};
