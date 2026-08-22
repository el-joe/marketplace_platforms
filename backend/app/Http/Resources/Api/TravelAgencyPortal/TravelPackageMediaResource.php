<?php

namespace App\Http\Resources\Api\TravelAgencyPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelPackageMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'media_type' => $this->media_type,
            'url' => $this->url(),
            'position' => $this->position,
        ];
    }
}
