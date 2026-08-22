<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Thin wrapper around the pre-rendered page array produced by
 * PageRendererService::render(). The renderer assembles blocks dynamically
 * per page type, so the array is passed through as-is rather than re-derived.
 */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
