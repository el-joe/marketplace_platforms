<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => Bilingual::pair($this->resource, 'name'),
            'slug' => $this->slug,
            'logo_url' => $this->resolveLogoUrl($this->logo_media_id),
            'description' => Bilingual::pair($this->resource, 'description'),
            'is_verified' => $this->is_verified,
        ];
    }

    private function resolveLogoUrl(?string $logoMediaId): ?string
    {
        if ($logoMediaId === null) {
            return null;
        }

        return str_contains($logoMediaId, '/') ? Storage::url($logoMediaId) : $logoMediaId;
    }
}
