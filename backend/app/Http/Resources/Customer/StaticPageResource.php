<?php

namespace App\Http\Resources\Customer;

use App\Models\StaticPage;
use App\Support\Bilingual;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaticPage
 */
class StaticPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = Bilingual::pair($this->resource, 'content');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => Bilingual::pair($this->resource, 'title'),
            // Sanitized on save in the admin; re-cleaned here as defense in depth.
            'content' => [
                'ar' => HtmlSanitizer::clean($content['ar']),
                'en' => HtmlSanitizer::clean($content['en']),
            ],
            // Raw per-language values (null when not set); clients fall back to `title`.
            'seo' => [
                'title' => Bilingual::pair($this->resource, 'seo_title'),
                'description' => Bilingual::pair($this->resource, 'seo_description'),
            ],
            'footer_group' => $this->footer_group,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
