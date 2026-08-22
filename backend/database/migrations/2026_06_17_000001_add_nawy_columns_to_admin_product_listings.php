<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_product_listings', function (Blueprint $table) {
            $table->enum('payment_options', ['cod_only', 'electronic_only', 'both'])
                ->notNull()
                ->default('both')
                ->after('currency');

            $table->enum('fulfillment_type', ['express', 'global', 'mixed'])
                ->notNull()
                ->default('mixed')
                ->after('payment_options');

            $table->tinyInteger('featured_in_nawy')
                ->default(1)
                ->after('fulfillment_type');

            $table->char('nawy_category_id', 36)
                ->nullable()
                ->after('featured_in_nawy');

            $table->foreign('nawy_category_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();

            $table->index(['featured_in_nawy', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_product_listings', function (Blueprint $table) {
            $table->dropForeign(['nawy_category_id']);
            $table->dropIndex(['featured_in_nawy', 'status']);
            $table->dropColumn(['payment_options', 'fulfillment_type', 'featured_in_nawy', 'nawy_category_id']);
        });
    }
};
