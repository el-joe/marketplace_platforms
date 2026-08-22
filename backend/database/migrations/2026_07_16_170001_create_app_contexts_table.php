<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_contexts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 50)->unique();
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->string('icon_path', 500)->nullable();
            $table->string('color_hex', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $contexts = [
            ['key' => 'main', 'name_en' => 'Main', 'name_ar' => 'الرئيسية', 'color_hex' => '#0F172A', 'sort_order' => 1],
            ['key' => 'nawy_now', 'name_en' => 'Nawy Now', 'name_ar' => 'ناوي ناو', 'color_hex' => '#FFCC00', 'sort_order' => 2],
            ['key' => 'classifieds', 'name_en' => 'Classifieds', 'name_ar' => 'المصنفات', 'color_hex' => '#2563EB', 'sort_order' => 3],
            ['key' => 'travel', 'name_en' => 'Travel', 'name_ar' => 'السفر', 'color_hex' => '#059669', 'sort_order' => 4],
        ];

        foreach ($contexts as $context) {
            \Illuminate\Support\Facades\DB::table('app_contexts')->insert(array_merge($context, [
                'id' => (string) Str::uuid(),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_contexts');
    }
};
