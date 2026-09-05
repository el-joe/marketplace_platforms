<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('categories')
            ->whereNotNull('slug')
            ->orderBy('id')
            ->select('id', 'slug')
            ->chunkById(500, function ($categories) use ($now) {
                $rows = $categories->map(fn ($category) => [
                    'id' => (string) Str::uuid(),
                    'slug_url' => $category->slug,
                    'sluggable_type' => \App\Models\Category::class,
                    'sluggable_id' => $category->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows) {
                    DB::table('slugs')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        DB::table('slugs')->where('sluggable_type', \App\Models\Category::class)->delete();
    }
};
