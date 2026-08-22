<?php

namespace App\Http\Resources\Carrier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FallbackStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // $this->resource is the array returned by the controller
        return $this->resource;
    }
}
