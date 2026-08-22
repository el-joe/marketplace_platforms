<?php

namespace App\Http\Resources\Carrier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveMapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // $this->resource is the array returned by LiveMapService::getCompanyMapData()
        return $this->resource;
    }
}
