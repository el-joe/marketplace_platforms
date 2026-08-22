<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_card_batches', function (Blueprint $table) {
            // Storefront visibility
            $table->boolean('is_purchasable')
                  ->default(false)
                  ->after('quantity')
                  ->comment('True = listed on customer storefront for purchase');

            // Customer-facing display
            $table->string('title_ar', 255)->nullable()->after('name');
            $table->string('title_en', 255)->nullable()->after('title_ar');
            $table->string('image_url')->nullable()->after('description')
                  ->comment('Card design image URL shown on storefront');

            // Purchase quantity limits per order
            $table->unsignedTinyInteger('min_quantity')->default(1)->after('is_purchasable');
            $table->unsignedTinyInteger('max_quantity')->default(10)->after('min_quantity');

            // Display order on storefront
            $table->unsignedTinyInteger('sort_order')->default(0)->after('max_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_batches', function (Blueprint $table) {
            $table->dropColumn([
                'is_purchasable', 'title_ar', 'title_en', 'image_url',
                'min_quantity', 'max_quantity', 'sort_order',
            ]);
        });
    }
};
