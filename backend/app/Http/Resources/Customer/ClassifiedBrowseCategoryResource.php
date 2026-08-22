<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * "category" block of GET /browse/classified/{id}.
 */
class ClassifiedBrowseCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'source_type' => 'classified',
            'name'        => Bilingual::pair($this->resource, 'name'),
            'slug'        => $this->slug,
            'icon'        => $this->icon,
            'link'        => "/browse/classified/{$this->id}",
        ];
    }
}
