<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateCategoryStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function handle(): void
    {
        Log::info('RecalculateCategoryStatsJob: starting');

        // Get product counts per direct category
        $directCounts = DB::table('products')
            ->whereNull('deleted_at')
            ->whereNotNull('category_id')
            ->select('category_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        // Get all categories with nested set columns
        $categories = DB::table('categories')
            ->whereNull('deleted_at')
            ->select('id', 'lft', 'rgt')
            ->get();

        // Build a map: category_id => total (subtree) product count
        foreach ($categories as $category) {
            // Sum products for all categories whose lft/rgt falls within this node's subtree
            $subtreeCount = DB::table('products as p')
                ->join('categories as c', 'c.id', '=', 'p.category_id')
                ->whereNull('p.deleted_at')
                ->whereNull('c.deleted_at')
                ->where('c.lft', '>=', $category->lft)
                ->where('c.rgt', '<=', $category->rgt)
                ->count();

            DB::table('categories')
                ->where('id', $category->id)
                ->update(['product_count' => $subtreeCount, 'updated_at' => now()]);
        }

        Log::info('RecalculateCategoryStatsJob: done', ['categories' => $categories->count()]);
    }
}
