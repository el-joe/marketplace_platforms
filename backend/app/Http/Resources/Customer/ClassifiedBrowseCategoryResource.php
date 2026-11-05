<?php

namespace App\Http\Resources\Customer;

use App\Models\ClassifiedCategory;
use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * "category" block of GET /browse/classified/{idOrSlug}: the category itself, its parent (for breadcrumbs) and
 * its visible sub-categories, so a category landing page needs no second request.
 *
 * @property ClassifiedCategory $resource
 */
class ClassifiedBrowseCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $parent = $this->resource->relationLoaded('parent') ? $this->resource->parent : null;

        return [
            'id' => $this->id,
            'source_type' => 'classified',
            'name' => Bilingual::pair($this->resource, 'name'),
            'slug' => $this->slug,
            'icon' => $this->icon,
            'image_url' => $this->image_url,
            'description' => Bilingual::pair($this->resource, 'description'),
            'link' => "/browse/classified/{$this->slug}",
            'parent' => $parent ? [
                'id' => $parent->id,
                'name' => Bilingual::pair($parent, 'name'),
                'slug' => $parent->slug,
            ] : null,
            'children' => $this->resource->relationLoaded('children')
                ? $this->resource->children->map(fn (ClassifiedCategory $child) => [
                    'id' => $child->id,
                    'name' => Bilingual::pair($child, 'name'),
                    'slug' => $child->slug,
                ])->values()->all()
                : [],
        ];
    }
}
