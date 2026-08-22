<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Entry in the `available_categories` list on the travel browse response.
 * Expects `packages_count` to be preloaded via withCount('packages').
 */
class TravelCategorySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => Bilingual::pair($this->resource, 'name'),
            'slug'          => $this->slug,
            'icon'          => $this->icon,
            'package_count' => $this->packages_count,
        ];
    }
}
