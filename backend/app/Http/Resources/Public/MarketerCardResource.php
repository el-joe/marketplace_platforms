<?php

namespace App\Http\Resources\Public;

use App\Models\MarketerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Card of the public marketers directory. Keeps every legacy key and adds
 * slug/gender/type/type_label/specialty. Expects marketer, marketer.marketerJobs,
 * bannerFile and avatarFile to be eager loaded.
 *
 * @mixin MarketerProfile
 */
class MarketerCardResource extends JsonResource
{
    public function __construct($resource, private readonly ?string $typeKey = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $marketer = $this->marketer;
        $locale = MarketerBannerResource::localeFor($request);
        $jobs = $marketer->marketerJobs;
        $job = ($this->typeKey ? $jobs->firstWhere('key', $this->typeKey) : null) ?? $jobs->first();
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $defaultLocale = config('app.frontend_default_locale', 'uae-en');

        return [
            'id' => $marketer->id,
            'slug' => $this->profile_slug,
            'name' => $marketer->name,
            'marketer_type' => $job?->key,
            'type' => $job?->key,
            'type_label' => $job ? ($locale === 'ar' ? $job->name_ar : $job->name_en) : null,
            'gender' => $this->gender?->value,
            'profile_slug' => $this->profile_slug,
            'profile_url' => $frontendUrl.'/'.$defaultLocale.'/marketer/'.$this->profile_slug,
            'banner_url' => $this->bannerFile?->url,
            'avatar_url' => $this->avatarFile?->url,
            'total_campaigns' => $marketer->total_campaigns,
            'total_conversions' => $marketer->total_conversions,
            'avatar_initial' => mb_substr($marketer->name, 0, 1),
            'specialty' => $locale === 'ar' ? ($this->specialty_ar ?: $this->specialty_en) : ($this->specialty_en ?: $this->specialty_ar),
            'specialty_ar' => $this->specialty_ar,
            'specialty_en' => $this->specialty_en,
            'ad_price' => $this->ad_price,
            'ad_price_currency' => $this->ad_price_currency,
            'classified_count' => (int) ($marketer->classified_count ?? 0),
        ];
    }
}
