<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Base-currency units of discount applied from loyalty point redemption.
            $table->bigInteger('loyalty_discount')->default(0)->after('discount');
            // Number of loyalty points consumed for this order.
            $table->decimal('loyalty_points_used', 10, 2)->default(0)->after('loyalty_discount');
            // Number of loyalty points awarded when sub-orders are delivered.
            $table->decimal('loyalty_points_earned', 10, 2)->default(0)->after('loyalty_points_used');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['loyalty_discount', 'loyalty_points_used', 'loyalty_points_earned']);
        });
    }
};
