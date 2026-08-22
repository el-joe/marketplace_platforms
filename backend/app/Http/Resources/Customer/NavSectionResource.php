<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a single unified nav section (products/classifieds/travel), whose
 * nodes are pre-shaped arrays produced by UnifiedCategoryService — same
 * per-node shape consumed by NavNodeResource elsewhere.
 */
class NavSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $section = [
            'section' => $this->resource['section'],
        ];

        if (isset($this->resource['label'])) {
            $section['label'] = $this->resource['label'];
        }

        if (isset($this->resource['link'])) {
            $section['link'] = $this->resource['link'];
        }

        $section['nodes'] = NavNodeResource::collection($this->resource['nodes'] ?? []);

        return $section;
    }
}
