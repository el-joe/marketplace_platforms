<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['delivery_label_en', 'delivery_label_ar'] as $col) {
            if (Schema::hasColumn('shipping_methods', $col)) {
                Schema::table('shipping_methods', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $t) {
            if (! Schema::hasColumn('shipping_methods', 'delivery_label_en')) {
                $t->string('delivery_label_en', 100)->nullable();
            }
            if (! Schema::hasColumn('shipping_methods', 'delivery_label_ar')) {
                $t->string('delivery_label_ar', 100)->nullable();
            }
        });
    }
};
