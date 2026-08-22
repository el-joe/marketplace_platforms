<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Category metadata portion of the GET /categories/{slug} response.
 * Does NOT include products or page_blocks — those are assembled separately
 * in CategoryController::show() since they have different caching lifecycles.
 */
class CategoryPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => Bilingual::pair($this->resource, 'name'),
            'slug'           => $this->slug,
            'breadcrumbs'    => $this->buildBreadcrumbs(),
            'subcategories'  => $this->directChildren(),
        ];
    }

    private function buildBreadcrumbs(): array
    {
        $crumbs = [];

        foreach ($this->ancestors()->get() as $ancestor) {
            $crumbs[] = [
                'id'   => $ancestor->id,
                'name' => Bilingual::pair($ancestor, 'name'),
                'slug' => $ancestor->slug,
            ];
        }

        $crumbs[] = [
            'id'   => $this->id,
            'name' => Bilingual::pair($this->resource, 'name'),
            'slug' => $this->slug,
        ];

        return $crumbs;
    }

    private function directChildren(): array
    {
        return $this->children()
            ->where('is_active', true)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($child) => [
                'id'            => $child->id,
                'name'          => Bilingual::pair($child, 'name'),
                'slug'          => $child->slug,
                'image_url'     => $child->image_url,
                'product_count' => (int) $child->product_count,
            ])
            ->toArray();
    }
}
