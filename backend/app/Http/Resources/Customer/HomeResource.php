<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Thin wrapper around the aggregate array built by HomeService::getHomeData().
 * The underlying data is assembled from many independent sub-services (nav tree,
 * page builder, dynamic sections, meta), so it is wrapped here as-is rather than
 * re-derived field-by-field, matching the pattern used by NavNodeResource for
 * other pre-shaped service arrays.
 */
class HomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pageBuilder = $this->resource['page_builder'] ?? null;

        return [
            'nav' => $this->resource['nav'] ?? [],
            'page_builder' => $pageBuilder,
            'has_page_builder' => $pageBuilder !== null && (
                !empty($pageBuilder['sections']) || !empty($pageBuilder['blocks'])
            ),
            'sections' => $this->resource['sections'] ?? [],
            'meta' => $this->resource['meta'] ?? [],
        ];
    }
}
