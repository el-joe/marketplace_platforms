<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->bigInteger('compare_at_price')->nullable()->after('price');
            $table->boolean('buy_box_eligible')->default(true)->after('low_stock_threshold');
            $table->timestampTz('buy_box_won_at')->nullable()->after('buy_box_eligible');
            $table->decimal('score', 8, 4)->nullable()->after('rating_count');
            $table->decimal('price_score', 5, 4)->nullable()->after('score');
            $table->decimal('fulfillment_score', 5, 4)->nullable()->after('price_score');
            $table->decimal('rating_score', 5, 4)->nullable()->after('fulfillment_score');
            $table->decimal('availability_score', 5, 4)->nullable()->after('rating_score');
            $table->timestampTz('calculated_at')->nullable()->after('availability_score');
            $table->timestampTz('next_recalculate_at')->nullable()->after('calculated_at');
            $table->index('score');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropIndex(['score']);
            $table->dropColumn([
                'compare_at_price',
                'buy_box_eligible',
                'buy_box_won_at',
                'score',
                'price_score',
                'fulfillment_score',
                'rating_score',
                'availability_score',
                'calculated_at',
                'next_recalculate_at',
            ]);
        });
    }
};
