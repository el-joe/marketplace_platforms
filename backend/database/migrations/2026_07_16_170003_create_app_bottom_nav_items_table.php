<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_bottom_nav_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('app_context_id');
            $table->uuid('country_id')->nullable();
            $table->tinyInteger('position');
            $table->enum('nav_type', ['home', 'categories', 'featured', 'account', 'cart', 'custom']);
            $table->string('label_en', 50);
            $table->string('label_ar', 50);
            $table->string('icon_name', 50);
            $table->string('deep_link', 200)->nullable();
            $table->boolean('is_center_featured')->default(false);
            $table->tinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['app_context_id', 'country_id', 'position']);

            $table->foreign('app_context_id')->references('id')->on('app_contexts')->onDelete('cascade');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
        });

        $mainContextId = DB::table('app_contexts')->where('key', 'main')->value('id');

        if ($mainContextId) {
            $now = now();
            $defaults = [
                ['position' => 1, 'nav_type' => 'home', 'label_en' => 'Home', 'label_ar' => 'الرئيسية', 'icon_name' => 'home', 'is_center_featured' => false, 'sort_order' => 1],
                ['position' => 2, 'nav_type' => 'categories', 'label_en' => 'Categories', 'label_ar' => 'الفئات', 'icon_name' => 'grid', 'is_center_featured' => false, 'sort_order' => 2],
                ['position' => 3, 'nav_type' => 'featured', 'label_en' => 'Nawy Now', 'label_ar' => 'ناوي ناو', 'icon_name' => 'star', 'is_center_featured' => true, 'sort_order' => 3],
                ['position' => 4, 'nav_type' => 'account', 'label_en' => 'Account', 'label_ar' => 'حسابي', 'icon_name' => 'user', 'is_center_featured' => false, 'sort_order' => 4],
                ['position' => 5, 'nav_type' => 'cart', 'label_en' => 'Cart', 'label_ar' => 'السلة', 'icon_name' => 'cart', 'is_center_featured' => false, 'sort_order' => 5],
            ];

            foreach ($defaults as $item) {
                DB::table('app_bottom_nav_items')->insert(array_merge($item, [
                    'id' => (string) Str::uuid(),
                    'app_context_id' => $mainContextId,
                    'country_id' => null,
                    'deep_link' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_bottom_nav_items');
    }
};
