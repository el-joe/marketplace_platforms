<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unified nav node — same shape for product/classified/travel nodes.
 * $this->resource is an array (not an Eloquent model) because NavigationService
 * builds the tree manually before handing it off here.
 */
class NavNodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type'     => $this->resource['type'] ?? $this->resource['source_type'],
            'id'       => $this->resource['id'],
            'name'     => $this->resource['name'],
            'slug'     => $this->resource['slug'],
            'icon'     => $this->resource['icon'] ?? null,
            'children' => NavNodeResource::collection($this->resource['children'] ?? []),
        ];
    }
}
