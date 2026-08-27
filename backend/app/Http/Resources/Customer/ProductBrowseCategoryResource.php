<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * "category" block of GET /browse/product/{id} — metadata, filterable
 * attributes, and direct subcategories for a product Category.
 */
class ProductBrowseCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => Bilingual::pair($this->resource, 'name'),
            'slug'           => $this->slug,
            'image_url'      => $this->image_url,
            'description'    => Bilingual::pair($this->resource, 'description'),
            'parent'         => $this->parent ? [
                'id'      => $this->parent->id,
                'name'    => Bilingual::pair($this->parent, 'name'),
                'slug'    => $this->parent->slug,
            ] : null,
            'attributes' => $this->attributes()->where('is_filterable', true)->with('values')->get()
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
                    ])->values()->all(),
                ])->values()->all(),
            'children' => $this->children()
                ->where('is_active', true)
                ->where('is_visible', true)
                ->orderBy('sort_order')
                ->with('primaryImage')
                ->get()
                ->map(fn ($child) => [
                    'id'        => $child->id,
                    'name'      => Bilingual::pair($child, 'name'),
                    'slug'      => $child->slug,
                    'icon'      => $child->icon,
                    'image_url' => $child->image_url,
                ])
                ->toArray(),
        ];
    }
}
