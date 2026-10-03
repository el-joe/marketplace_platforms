<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('qr_code_path')->nullable()->after('size_guide_image');
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->string('qr_code_path')->nullable()->after('external_api_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('qr_code_path');
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropColumn('qr_code_path');
        });
    }
};
