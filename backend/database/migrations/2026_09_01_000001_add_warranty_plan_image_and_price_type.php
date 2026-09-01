<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_plans', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('sort_order');
            $table->enum('price_type', ['flat', 'percentage'])->default('flat')->after('price');
            $table->decimal('price_pct', 5, 2)->nullable()->after('price_type');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->char('warranty_plan_id', 36)->nullable()->after('selected_shipping_method_id');
            $table->foreign('warranty_plan_id')->references('id')->on('warranty_plans')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['warranty_plan_id']);
            $table->dropColumn('warranty_plan_id');
        });

        Schema::table('warranty_plans', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'price_type', 'price_pct']);
        });
    }
};
