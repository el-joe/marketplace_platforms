<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\Notification;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReviewProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly string $productId)
    {
    }

    public function handle(): void
    {
        $product = Product::find($this->productId);
        if (!$product)
            return;

        $score = $this->computeScore($product);

        $product->update(['ai_quality_score' => $score]);

        if ($score < 5) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'product_quality_low',
                'notifiable_type' => Admin::class,
                'notifiable_id' => $product->created_by_admin_id,
                'data' => json_encode([
                    'message' => "Product \"{$product->name_en}\" has a low quality score ({$score}/10).",
                    'product_id' => $product->id,
                    'score' => $score,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function computeScore(Product $product): int
    {
        $score = 0;

        // Title quality (2 pts)
        $enLen = mb_strlen($product->name_en ?? '');
        $arLen = mb_strlen($product->name_ar ?? '');
        if ($enLen >= 10 && $enLen <= 200)
            $score++;
        if ($arLen >= 5)
            $score++;

        // Description (3 pts)
        $descLen = mb_strlen(strip_tags($product->description_en ?? ''));
        if ($descLen >= 50)
            $score++;
        if ($descLen >= 200)
            $score++;
        if (mb_strlen(strip_tags($product->description_ar ?? '')) >= 50)
            $score++;

        // Short description (1 pt)
        if (mb_strlen($product->short_desc_en ?? '') >= 20)
            $score++;

        // Images (2 pts)
        $imageCount = DB::table('product_images')->where('product_id', $product->id)->count();
        if ($imageCount >= 1)
            $score++;
        if ($imageCount >= 3)
            $score++;

        // SEO (2 pts)
        if (mb_strlen($product->seo_title_en ?? '') >= 10)
            $score++;
        if (mb_strlen($product->seo_description_en ?? '') >= 50)
            $score++;

        return min($score, 10);
    }
}
