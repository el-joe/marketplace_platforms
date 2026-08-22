<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_image_items', function (Blueprint $table): void {
            $table->boolean('is_paid')->default(false)->after('is_active');
        });

        Schema::table('slider_slides', function (Blueprint $table): void {
            $table->boolean('is_paid')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('ad_image_items', function (Blueprint $table): void {
            $table->dropColumn('is_paid');
        });

        Schema::table('slider_slides', function (Blueprint $table): void {
            $table->dropColumn('is_paid');
        });
    }
};
