<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->uuid('shipping_method_id')->nullable()->after('fulfillment_status');
            $table->json('shipping_method_snapshot')->nullable()->after('shipping_method_id')
                ->comment('Snapshot of method name, badge, delivery_label at order time');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('shipping_method_id')
                ->references('id')->on('shipping_methods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['shipping_method_id']);
            $table->dropColumn(['shipping_method_id', 'shipping_method_snapshot']);
        });
    }
};
