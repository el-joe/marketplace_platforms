<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->tinyInteger('supports_virtual_tryon')->default(0)
                ->comment('Enable for apparel/shoes categories')
                ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('supports_virtual_tryon');
        });
    }
};
