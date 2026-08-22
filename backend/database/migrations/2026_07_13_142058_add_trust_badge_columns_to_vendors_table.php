<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->boolean('easy_returns_enabled')->default(true);
            $table->boolean('secure_payments_enabled')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['warranty_months', 'easy_returns_enabled', 'secure_payments_enabled']);
        });
    }
};
