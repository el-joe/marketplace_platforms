<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketers', function (Blueprint $table) {
            $table->dropColumn('marketer_type');
        });
    }

    public function down(): void
    {
        Schema::table('marketers', function (Blueprint $table) {
            $table->enum('marketer_type', ['influencer', 'affiliate'])->nullable();
        });
    }
};
