<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Classified category tree node, shaped to match CategoryTreeResource so both
 * can be merged into a single customer-facing category array.
 */
class ClassifiedCategoryTreeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'type'      => 'classified',
            'name'      => Bilingual::pair($this->resource, 'name'),
            'slug'      => $this->slug,
            'parent_id' => $this->parent_id,
            'icon'      => $this->icon,
            'children'  => ClassifiedCategoryTreeResource::collection($this->whenLoaded('children'))->resolve(),
        ];
    }
}
