<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gift_card_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('gift_card_batches', 'is_purchasable')) {
                $table->boolean('is_purchasable')
                    ->default(false)
                    ->after('quantity')
                    ->comment('True = listed on customer storefront for purchase');
            }

            if (! Schema::hasColumn('gift_card_batches', 'title_ar')) {
                $table->string('title_ar', 255)->nullable()->after('name');
            }

            if (! Schema::hasColumn('gift_card_batches', 'title_en')) {
                $table->string('title_en', 255)->nullable()->after('title_ar');
            }

            if (! Schema::hasColumn('gift_card_batches', 'image_url')) {
                $table->string('image_url')->nullable()->after('description')
                    ->comment('Card design image URL shown on storefront');
            }

            if (! Schema::hasColumn('gift_card_batches', 'min_quantity')) {
                $table->unsignedTinyInteger('min_quantity')->default(1)->after('is_purchasable');
            }

            if (! Schema::hasColumn('gift_card_batches', 'max_quantity')) {
                $table->unsignedTinyInteger('max_quantity')->default(10)->after('min_quantity');
            }

            if (! Schema::hasColumn('gift_card_batches', 'sort_order')) {
                $table->unsignedTinyInteger('sort_order')->default(0)->after('max_quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gift_card_batches', function (Blueprint $table) {
            foreach (['is_purchasable', 'title_ar', 'title_en', 'image_url', 'min_quantity', 'max_quantity', 'sort_order'] as $column) {
                if (Schema::hasColumn('gift_card_batches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
