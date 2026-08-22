<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->integer('nawy_sort_order')->default(0)->after('sort_order');
            $table->string('nawy_icon_path', 500)->nullable()->after('nawy_sort_order');
            $table->boolean('nawy_is_featured')->default(false)->after('nawy_icon_path');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['nawy_sort_order', 'nawy_icon_path', 'nawy_is_featured']);
        });
    }
};
