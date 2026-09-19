<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_custom_attributes', function (Blueprint $table) {
            $table->string('type', 20)->default('text')->after('label'); // text|number|select|checkbox|notes
            $table->json('options')->nullable()->after('type');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('size_guide_image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_custom_attributes', fn (Blueprint $t) => $t->dropColumn(['type', 'options']));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('size_guide_image'));
    }
};
