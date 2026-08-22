<?php

namespace App\Services;

use App\Jobs\CreateProductCountrySettingsJob;
use App\Jobs\ReviewProductJob;
use App\Models\Product;
use App\Models\ProductCountrySetting;
use App\Models\ProductImage;
use App\Models\ProductImageHash;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    // ─── Create ──────────────────────────────────────────────────────────────

    public function create(array $data, string $adminId): Product
    {
        $product = Product::create(array_merge($data, [
            'created_by_admin_id' => $adminId,
            'status' => $data['status'] ?? 'draft',
            'slug' => $this->resolveSlug($data['slug'] ?? null, $data['name_en']),
        ]));

        // Create one default variant when product has no variant matrix
        if (!($data['has_variants'] ?? false)) {
            $variantData = $data['variants'][0] ?? [];
            ProductVariant::create([
                'id' => (string) Str::uuid(),
                'product_id' => $product->id,
                'sku' => $variantData['sku'] ?: 'SKU-' . strtoupper(Str::random(8)),
                'barcode' => $variantData['barcode'] ?? null,
                'weight_grams' => isset($variantData['weight_grams']) ? (int) $variantData['weight_grams'] : null,
                'length_cm' => isset($variantData['length_cm']) ? (float) $variantData['length_cm'] : null,
                'width_cm' => isset($variantData['width_cm']) ? (float) $variantData['width_cm'] : null,
                'height_cm' => isset($variantData['height_cm']) ? (float) $variantData['height_cm'] : null,
                'is_default' => true,
                'is_active' => true,
                'position' => 0,
            ]);
        }

        CreateProductCountrySettingsJob::dispatch($product->id);
        ReviewProductJob::dispatch($product->id);

        return $product;
    }

    // ─── Update ──────────────────────────────────────────────────────────────

    public function update(Product $product, array $data): Product
    {
        if (isset($data['slug']) && blank($data['slug'])) {
            unset($data['slug']);
        }

        $product->update($data);
        return $product->fresh();
    }

    // ─── Variant Generation ──────────────────────────────────────────────────

    public function generateVariants(Product $product, array $attributeIds): array
    {
        // Load attribute values for each selected attribute
        $groups = [];
        foreach ($attributeIds as $attrId) {
            $values = DB::table('attribute_values')
                ->where('attribute_id', $attrId)
                ->orderBy('sort_order')
                ->get(['id', 'value_en', 'value_ar'])
                ->toArray();
            if ($values) {
                $groups[] = $values;
            }
        }

        if (empty($groups)) {
            return [];
        }

        // Cartesian product of all attribute value groups
        $combinations = $this->cartesian($groups);

        $created = [];
        foreach ($combinations as $i => $combo) {
            $variantName = implode(' / ', array_column($combo, 'value_en'));

            $variant = ProductVariant::create([
                'id' => (string) Str::uuid(),
                'product_id' => $product->id,
                'variant_name' => $variantName,
                'sku' => '',  // admin fills in
                'is_default' => $i === 0,
                'is_active' => true,
                'position' => $i,
            ]);

            // Store attribute values for this variant
            foreach ($combo as $val) {
                DB::table('product_variant_attributes')->insert([
                    'id' => (string) Str::uuid(),
                    'product_variant_id' => $variant->id,
                    'attribute_value_id' => $val->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $created[] = $variant;
        }

        return $created;
    }

    // ─── GTIN duplicate check ────────────────────────────────────────────────

    public function checkGtinDuplicate(string $gtin, ?string $exceptId = null): ?Product
    {
        return Product::where('gtin', $gtin)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->whereNull('deleted_at')
            ->first();
    }

    // ─── Images ──────────────────────────────────────────────────────────────

    public function uploadImage(Product $product, UploadedFile $file): ProductImage
    {
        $path = $file->store("products/{$product->id}/images", 'public');
        $md5 = md5_file($file->getRealPath());

        // Perceptual hash (simple average hash if GD available)
        $pHash = $this->computePerceptualHash($file->getRealPath());

        // Duplicate detection
        $existing = ProductImageHash::where('md5_hash', $md5)->first();
        if ($existing) {
            Log::info("Duplicate product image detected for product {$product->id}, md5={$md5}");
        }

        $position = ProductImage::where('product_id', $product->id)->max('position') + 1;
        $isPrimary = !ProductImage::where('product_id', $product->id)->where('is_primary', true)->exists();

        $image = ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'position' => $position,
            'is_primary' => $isPrimary,
        ]);

        ProductImageHash::create([
            'product_image_id' => $image->id,
            'md5_hash' => $md5,
            'perceptual_hash' => $pHash,
        ]);

        return $image;
    }

    public function reorderImages(Product $product, array $orderedIds): void
    {
        foreach ($orderedIds as $pos => $imageId) {
            ProductImage::where('id', $imageId)
                ->where('product_id', $product->id)
                ->update(['position' => $pos]);
        }
    }

    public function deleteImage(ProductImage $image): void
    {
        Storage::disk($image->disk ?? 'public')->delete($image->path);
        $image->hash?->delete();
        $image->delete();
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function resolveSlug(?string $slug, string $nameEn): string
    {
        $slug = $slug ?: Str::slug($nameEn);
        $base = $slug;
        $i = 1;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function cartesian(array $groups): array
    {
        $result = [[]];
        foreach ($groups as $group) {
            $tmp = [];
            foreach ($result as $existing) {
                foreach ($group as $item) {
                    $tmp[] = array_merge($existing, [$item]);
                }
            }
            $result = $tmp;
        }
        return $result;
    }

    private function computePerceptualHash(string $path): ?string
    {
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagescale')) {
            return null;
        }
        try {
            $img = @imagecreatefromstring(file_get_contents($path));
            if (!$img)
                return null;
            $small = imagescale($img, 8, 8, IMG_BICUBIC);
            imagedestroy($img);
            $gray = [];
            $sum = 0;
            for ($y = 0; $y < 8; $y++) {
                for ($x = 0; $x < 8; $x++) {
                    $rgb = imagecolorat($small, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    $lum = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
                    $gray[] = $lum;
                    $sum += $lum;
                }
            }
            imagedestroy($small);
            $avg = $sum / 64;
            $hash = '';
            foreach ($gray as $lum) {
                $hash .= $lum >= $avg ? '1' : '0';
            }
            return base_convert($hash, 2, 16);
        } catch (\Throwable) {
            return null;
        }
    }
}
