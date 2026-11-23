<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\BannerDeviceTarget;
use App\Models\Banner;
use Illuminate\Validation\Validator;

/**
 * A banner must carry at least one image (English or Arabic) for every device it is shown on,
 * otherwise the storefront silently hides it there. Images already saved on the banner count.
 */
trait RequiresBannerDeviceImages
{
    /** @return list<callable(Validator): void> */
    protected function deviceImageChecks(?Banner $banner): array
    {
        return [
            function (Validator $validator) use ($banner): void {
                $target = BannerDeviceTarget::tryFrom((string) $this->input('device_target'));
                if ($target === null || $validator->errors()->hasAny(['desktop_image_en', 'desktop_image_ar', 'mobile_image_en', 'mobile_image_ar'])) {
                    return;
                }

                $saved = $banner
                    ? $banner->files()->whereIn('file_type', ['banner_desktop_en', 'banner_desktop_ar', 'banner_mobile_en', 'banner_mobile_ar'])->pluck('file_type')->all()
                    : [];
                $has = fn (string $device): bool => $this->hasFile("{$device}_image_en")
                    || $this->hasFile("{$device}_image_ar")
                    || in_array("banner_{$device}_en", $saved, true)
                    || in_array("banner_{$device}_ar", $saved, true);

                if ($target === BannerDeviceTarget::App) {
                    if (! $has('mobile') && ! $has('desktop')) {
                        $validator->errors()->add('mobile_image_en', __('admin.banners.image_required_any'));
                    }

                    return;
                }

                $devices = match ($target) {
                    BannerDeviceTarget::Desktop => ['desktop'],
                    BannerDeviceTarget::Mobile => ['mobile'],
                    default => ['desktop', 'mobile'],
                };

                foreach ($devices as $device) {
                    if (! $has($device)) {
                        $validator->errors()->add("{$device}_image_en", __("admin.banners.image_required_{$device}"));
                    }
                }
            },
        ];
    }
}
