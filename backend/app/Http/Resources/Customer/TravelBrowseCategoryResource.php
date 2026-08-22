<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * "category" block of GET /browse/travel/{id} (and /travel). $resource may
 * be null, representing the "all travel" pseudo-category.
 */
class TravelBrowseCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $travelCategory = $this->resource;

        return [
            'id'                   => $travelCategory?->id,
            'source_type'          => 'travel',
            'name'                 => $travelCategory
                ? Bilingual::pair($travelCategory, 'name')
                : ['ar' => 'كل رحلات السفر', 'en' => 'All Travel'],
            'slug'                 => $travelCategory?->slug ?? 'all',
            'link'                 => '/travel',
            'travel_category_slug' => $travelCategory?->slug,
        ];
    }
}
