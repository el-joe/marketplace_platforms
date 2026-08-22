<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrandService
{
    public function create(array $data): Brand
    {
        return Brand::create(array_merge($data, [
            'slug' => $this->resolveSlug($data['slug'] ?? null, $data['name_en']),
        ]));
    }

    public function update(Brand $brand, array $data): Brand
    {
        if (isset($data['slug']) && blank($data['slug'])) {
            unset($data['slug']);
        }
        $brand->update($data);
        return $brand->fresh();
    }

    private function resolveSlug(?string $slug, string $nameEn): string
    {
        $slug = $slug ?: Str::slug($nameEn);
        $base = $slug;
        $i = 1;
        while (DB::table('brands')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
