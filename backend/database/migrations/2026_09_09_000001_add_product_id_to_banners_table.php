<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->uuid('product_id')->nullable()->after('placement_code')
                ->comment('If set, this banner is shown only on this product page');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
