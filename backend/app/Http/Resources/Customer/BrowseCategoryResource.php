<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Common "category" envelope block for all 3 browse verticals.
 * Wrap with: new BrowseCategoryResource($model, $type)
 */
class BrowseCategoryResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly string $type)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'type'          => $this->type,
            'id'            => $this->id,
            'name'          => Bilingual::pair($this->resource, 'name'),
            'slug'          => $this->slug,
            'image_url'     => $this->type === 'product' ? $this->image_url : null,
            'brands'        => $this->type === 'product'
                ? $this->brands->map(fn ($brand) => [
                    'id'       => $brand->id,
                    'name'     => Bilingual::pair($brand, 'name'),
                    'slug'     => $brand->slug,
                    'logo_url' => $brand->logo_url,
                ])->values()
                : [],
            'breadcrumbs'   => $this->buildBreadcrumbs(),
            'subcategories' => $this->buildSubcategories(),
        ];
    }

    private function buildBreadcrumbs(): array
    {
        $crumbs = [];

        if ($this->type === 'product') {
            foreach ($this->ancestors()->get() as $ancestor) {
                $crumbs[] = ['id' => $ancestor->id, 'name' => Bilingual::pair($ancestor, 'name'), 'slug' => $ancestor->slug];
            }
        } else {
            $chain = $this->ancestorChain();
            foreach (array_reverse($chain) as $ancestor) {
                $crumbs[] = ['id' => $ancestor->id, 'name' => Bilingual::pair($ancestor, 'name'), 'slug' => $ancestor->slug];
            }
        }

        $crumbs[] = ['id' => $this->id, 'name' => Bilingual::pair($this->resource, 'name'), 'slug' => $this->slug];

        return $crumbs;
    }

    private function buildSubcategories(): array
    {
        if ($this->type === 'product') {
            return $this->children()
                ->where('is_active', true)
                ->where('is_visible', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($c) => [
                    'id'        => $c->id,
                    'name'      => Bilingual::pair($c, 'name'),
                    'slug'      => $c->slug,
                    'type'      => 'product',
                    'image_url' => $c->image_url,
                    'brands'    => $c->brands->map(fn ($brand) => [
                        'id'       => $brand->id,
                        'name'     => Bilingual::pair($brand, 'name'),
                        'slug'     => $brand->slug,
                        'logo_url' => $brand->logo_url,
                    ])->values(),
                ])
                ->toArray();
        }

        return $this->children()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => [
                'id'   => $c->id,
                'name' => Bilingual::pair($c, 'name'),
                'slug' => $c->slug,
                'type' => $this->type,
            ])
            ->toArray();
    }

    /** Walk up parent_id chain for adjacency-list models (classified, travel). */
    private function ancestorChain(): array
    {
        $ancestors = [];
        $node = $this->resource;

        while ($node->parent_id) {
            $node = $node->parent()->first();
            if (!$node) {
                break;
            }
            $ancestors[] = $node;
        }

        return $ancestors;
    }
}
