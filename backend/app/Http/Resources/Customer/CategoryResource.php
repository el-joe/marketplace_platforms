<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'slug'      => $this->slug,
            'name'      => Bilingual::pair($this->resource, 'name'),
            'image_url' => $this->image_url,
            'product_count' => (int) $this->product_count,
            'brands'    => $this->brandsInSubtree()->get()->map(fn ($brand) => [
                'id'       => $brand->id,
                'name'     => Bilingual::pair($brand, 'name'),
                'slug'     => $brand->slug,
                'logo_url' => $brand->logo_url,
            ])->values()->all(),
            'children'  => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
