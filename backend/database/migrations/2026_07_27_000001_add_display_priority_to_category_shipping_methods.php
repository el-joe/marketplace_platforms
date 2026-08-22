<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('category_shipping_methods', function (Blueprint $table) {
            $table->unsignedInteger('display_priority')->default(0)->after('is_available_for_merchant_fbp')
                ->comment('Order this method appears for the category — lower = shown first');
        });
    }

    public function down(): void
    {
        Schema::table('category_shipping_methods', function (Blueprint $table) {
            $table->dropColumn('display_priority');
        });
    }
};
