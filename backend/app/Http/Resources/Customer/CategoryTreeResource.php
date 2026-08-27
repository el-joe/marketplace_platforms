<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full recursive category tree node for nav/menu responses.
 * product_count comes from the column maintained by RecalculateCategoryStatsJob.
 */
class CategoryTreeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'type'          => 'product',
            'name'          => Bilingual::pair($this->resource, 'name'),
            'slug'          => $this->slug,
            'parent_id'     => $this->parent_id,
            'image_url'     => $this->image_url,
            'product_count' => (int) $this->product_count,
            'brands'        => $this->brandsInSubtree()->get()->map(fn ($brand) => [
                'id'       => $brand->id,
                'name'     => Bilingual::pair($brand, 'name'),
                'slug'     => $brand->slug,
                'logo_url' => $brand->logo_url,
            ])->values()->all(),
            'attributes'    => $this->resource->attributes()->where('is_filterable', true)->with('values')->get()
                ->map(fn ($attribute) => [
                    'id'          => $attribute->id,
                    'code'        => $attribute->code,
                    'name'        => Bilingual::pair($attribute, 'name'),
                    'type'        => $attribute->type->value,
                    'unit'        => $attribute->unit,
                    'is_required' => (bool) $attribute->pivot->is_required,
                    'values'      => $attribute->values->map(fn ($value) => [
                        'id'        => $value->id,
                        'value'     => Bilingual::pair($value, 'value'),
                        'color_hex' => $value->color_hex,
                        'swatch_image_url' => $value->swatch_image_url,
                    ])->values()->all(),
                ])->values()->all(),
            'children'      => CategoryTreeResource::collection($this->children)->resolve(),
        ];
    }
}
