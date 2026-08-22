<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassifiedCategoryTreeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'name_en'                  => $this->name_en,
            'name_ar'                  => $this->name_ar,
            'slug'                     => $this->slug,
            'icon'                     => $this->icon,
            'requires_location_map'    => $this->requires_location_map,
            'requires_sketch_upload'   => $this->requires_sketch_upload,
            'required_attachment_types'=> $this->required_attachment_types ?? [],
            'has_contract'             => (bool) $this->contract_template_id,
            'children'                 => self::collection($this->whenLoaded('children')),
        ];
    }
}
