<?php

namespace App\Http\Resources\Public;

use App\Models\Banner;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public marketer banner (list hero/strip and profile slider). Localized by request locale,
 * with the image slot of the other locale as fallback. Expects `files` to be eager loaded.
 *
 * @mixin Banner
 */
class MarketerBannerResource extends JsonResource
{
    public static function localeFor(Request $request): string
    {
        $raw = (string) ($request->query('locale') ?: explode(',', (string) $request->header('Accept-Language', ''))[0]);
        $raw = strtolower(trim(explode(';', $raw)[0]));

        if ($raw === '') {
            return app()->getLocale() === 'en' ? 'en' : 'ar';
        }

        // Accepts "ar", "en-US", "uae-ar" and similar frontend locale codes.
        return str_ends_with($raw, 'ar') || str_starts_with($raw, 'ar') ? 'ar' : 'en';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = self::localeFor($request);
        $other = $locale === 'ar' ? 'en' : 'ar';
        $country = $request->attributes->get('country');

        $localized = fn (string $base) => $this->{$base.'_'.$locale} ?: $this->{$base.'_'.$other};
        $image = fn (string $device) => ($this->files->firstWhere('file_type', "banner_{$device}_{$locale}")
            ?? $this->files->firstWhere('file_type', "banner_{$device}_{$other}"))?->full_path;

        return [
            'id' => $this->id,
            'title' => $localized('title'),
            'subtitle' => $localized('subtitle'),
            'cta_label' => $localized('cta_label'),
            'link' => $this->cta_url ?: $this->marketerProfilePath(),
            'link_type' => $this->link_type?->value,
            'link_reference_id' => $this->link_reference_id,
            'image_desktop' => $image('desktop'),
            'image_mobile' => $image('mobile'),
            'priority' => (int) $this->priority,
            'click_url' => $country instanceof Country
                ? route('customer.banners.click', [$country->site_code, $this->id])
                : null,
        ];
    }

    /**
     * Fallback link for banners without a CTA URL: the details page of the one marketer the
     * banner was created for (ambiguous when several marketers are selected, so no link then).
     */
    private function marketerProfilePath(): ?string
    {
        if (! $this->relationLoaded('marketers') || $this->marketers->count() !== 1) {
            return null;
        }

        $slug = $this->marketers->first()->marketerProfile?->profile_slug;

        return $slug ? '/marketer/'.$slug : null;
    }
}
