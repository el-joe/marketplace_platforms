<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Thin wrapper around the aggregate array built by HomeService::getHomeData().
 */
class HomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pageBuilder = $this->resource['page_builder'] ?? null;

        return [
            'page_builder' => $pageBuilder,
            'has_page_builder' => $pageBuilder !== null && (
                !empty($pageBuilder['sections']) || !empty($pageBuilder['blocks'])
            ),
            'meta' => $this->resource['meta'] ?? [],
        ];
    }
}
