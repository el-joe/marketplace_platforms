<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Classified category tree node, shaped to match CategoryTreeResource so both
 * can be merged into a single customer-facing category array.
 *
 * `placements` tells each storefront surface (nav, landing, home) whether the admin put the section there.
 */
class ClassifiedCategoryTreeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'classified',
            'name' => Bilingual::pair($this->resource, 'name'),
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'icon' => $this->icon,
            'image_url' => $this->image_url,
            'description' => Bilingual::pair($this->resource, 'description'),
            'placements' => $this->placementList(),
            'children' => $this->when(
                $this->relationLoaded('children'),
                fn () => ClassifiedCategoryTreeResource::collection($this->children)->resolve(),
                [],
            ),
        ];
    }
}
