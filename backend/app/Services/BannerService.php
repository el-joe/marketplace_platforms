<?php

namespace App\Services;

use App\Enums\BannerDeviceTarget;
use App\Models\Banner;
use App\Models\File;
use App\Models\Marketer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BannerService
{
    private const DISK = 'public';

    private const IMAGE_FILE_TYPES = ['banner_desktop_en', 'banner_desktop_ar', 'banner_mobile_en', 'banner_mobile_ar'];

    /**
     * Store a banner image and create a files record.
     *
     * @param  string  $slot  'desktop_en' | 'desktop_ar' | 'mobile_en' | 'mobile_ar'
     *                        (bare 'desktop' / 'mobile' accepted as legacy aliases for 'en')
     */
    public function storeImage(UploadedFile $file, Banner $banner, string $slot = 'desktop_en'): File
    {
        $slot = $this->normalizeSlot($slot);

        // Remove existing image for this slot
        $this->deleteImageBySlot($banner, $slot);

        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension();
        $key = 'banners/'.$banner->id.'/'.$slot.'_'.Str::random(8).'.'.$ext;
        $path = $file->storeAs('banners/'.$banner->id, $slot.'_'.Str::random(8).'.'.$ext, self::DISK);
        $isDesktop = str_starts_with($slot, 'desktop');

        return File::create([
            'key' => $key,
            'path' => $path,
            'storage_type' => self::DISK,
            'file_type' => 'banner_'.$slot,
            'mime_type' => $file->getMimeType(),
            'extension' => $ext,
            'size' => $file->getSize(),
            'model_type' => Banner::class,
            'model_id' => $banner->id,
            'is_primary' => $isDesktop ? 1 : 0,
            'position' => $isDesktop ? 0 : 1,
        ]);
    }

    /**
     * Normalize a legacy bare 'desktop'/'mobile' slot to the 'en' language slot.
     */
    private function normalizeSlot(string $slot): string
    {
        return match ($slot) {
            'desktop' => 'desktop_en',
            'mobile' => 'mobile_en',
            default => $slot,
        };
    }

    /**
     * Get an image for a banner by slot ('desktop_en', 'desktop_ar', 'mobile_en', 'mobile_ar').
     */
    public function getImage(Banner $banner, string $slot): ?File
    {
        return $banner->files()
            ->where('file_type', 'banner_'.$this->normalizeSlot($slot))
            ->first();
    }

    /**
     * Get the desktop (EN) image for a banner. Kept for backward compatibility.
     */
    public function getDesktopImage(Banner $banner): ?File
    {
        return $this->getImage($banner, 'desktop_en');
    }

    /**
     * Get the mobile (EN) image for a banner. Kept for backward compatibility.
     */
    public function getMobileImage(Banner $banner): ?File
    {
        return $this->getImage($banner, 'mobile_en');
    }

    /**
     * Resolve the active banner for a customer-facing placement (e.g. 'cart_banner'),
     * respecting country, audience and the active date window. Ties broken by priority.
     */
    public function getActivePlacement(string $placementCode, ?string $countryId, string $audience = 'guest', ?string $productId = null): ?Banner
    {
        $now = now();

        return Banner::with('files')
            ->where('placement_code', $placementCode)
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->where(fn ($q) => $q->whereNull('country_id')->orWhere('country_id', $countryId))
            ->whereIn('audience', ['all', $audience])
            ->where(function ($q) use ($productId) {
                $q->whereNull('product_id');
                if ($productId) {
                    $q->orWhere('product_id', $productId);
                }
            })
            // Prefer a banner scoped to this specific product over a generic one.
            ->orderByRaw('product_id is null asc')
            ->orderByDesc('priority')
            ->first();
    }

    /**
     * Resolve the live banner for each web device of a customer-facing placement in one query.
     * A banner only competes for the devices it targets (all → both, desktop → desktop, mobile → mobile,
     * app → none) and only when it has at least one image. Ordered by product scope, priority, then newest.
     *
     * @return array{desktop: ?Banner, mobile: ?Banner}
     */
    public function getActivePlacementByDevice(string $placementCode, ?string $countryId, string $audience = 'guest', ?string $productId = null): array
    {
        $candidates = Banner::with('files')
            ->where('placement_code', $placementCode)
            ->live($countryId)
            ->whereIn('audience', ['all', $audience])
            ->whereIn('device_target', [BannerDeviceTarget::All->value, BannerDeviceTarget::Desktop->value, BannerDeviceTarget::Mobile->value])
            ->where(function ($q) use ($productId) {
                $q->whereNull('product_id');
                if ($productId) {
                    $q->orWhere('product_id', $productId);
                }
            })
            ->orderByRaw('product_id is null asc')
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Banner $banner) => $banner->files->contains(fn (File $f) => in_array($f->file_type, self::IMAGE_FILE_TYPES, true)));

        $pick = fn (string $device) => $candidates->first(
            fn (Banner $banner) => in_array($device, $this->targetedWebDevices($banner), true)
        );

        return ['desktop' => $pick('desktop'), 'mobile' => $pick('mobile')];
    }

    /**
     * Web devices a banner is shown on.
     *
     * @return list<'desktop'|'mobile'>
     */
    public function targetedWebDevices(Banner $banner): array
    {
        return match ($banner->device_target) {
            BannerDeviceTarget::Desktop => ['desktop'],
            BannerDeviceTarget::Mobile => ['mobile'],
            BannerDeviceTarget::App => [],
            default => ['desktop', 'mobile'],
        };
    }

    /**
     * Storefront image URLs of a banner per (device, locale), already resolved with fallbacks and device
     * targeting. Uses the loaded `files` relation.
     *
     * @param  list<'desktop'|'mobile'>|null  $devices  restrict to these devices (default: the banner's targeted devices)
     * @return array{desktop_image_url: ?string, mobile_image_url: ?string, desktop_image_url_ar: ?string, mobile_image_url_ar: ?string}
     */
    public function resolvedImageUrls(Banner $banner, ?array $devices = null): array
    {
        $slots = [];
        foreach (['desktop_en', 'desktop_ar', 'mobile_en', 'mobile_ar'] as $slot) {
            $slots[$slot] = $banner->files->firstWhere('file_type', 'banner_'.$slot)?->full_path;
        }

        $targeted = $this->targetedWebDevices($banner);
        $devices = $devices === null ? $targeted : array_values(array_intersect($devices, $targeted));

        return self::resolveImageGrid($slots, $devices);
    }

    /**
     * Pick the image for every (device, locale) pair. Fallback order for device D and locale L:
     *   1. exact (D, L)
     *   2. same device, other locale
     *   3. other device, same locale
     *   4. other device, other locale
     * A device the banner does not target always gets null, so the storefront hides the banner there.
     *
     * @param  array<string, ?string>  $slots  keys desktop_en, desktop_ar, mobile_en, mobile_ar
     * @param  list<string>  $devices  devices the banner is shown on
     * @return array{desktop_image_url: ?string, mobile_image_url: ?string, desktop_image_url_ar: ?string, mobile_image_url_ar: ?string}
     */
    public static function resolveImageGrid(array $slots, array $devices): array
    {
        $pick = function (string $device, string $locale) use ($slots, $devices): ?string {
            if (! in_array($device, $devices, true)) {
                return null;
            }

            $otherDevice = $device === 'desktop' ? 'mobile' : 'desktop';
            $otherLocale = $locale === 'en' ? 'ar' : 'en';

            foreach ([[$device, $locale], [$device, $otherLocale], [$otherDevice, $locale], [$otherDevice, $otherLocale]] as [$d, $l]) {
                if (! empty($slots["{$d}_{$l}"])) {
                    return $slots["{$d}_{$l}"];
                }
            }

            return null;
        };

        return [
            'desktop_image_url' => $pick('desktop', 'en'),
            'mobile_image_url' => $pick('mobile', 'en'),
            'desktop_image_url_ar' => $pick('desktop', 'ar'),
            'mobile_image_url_ar' => $pick('mobile', 'ar'),
        ];
    }

    /**
     * Live banners for the marketers list page (placement 'marketers_list_hero' | 'marketers_list_strip').
     * Only global banners qualify; $gender (male|female|null = both tabs) is matched against gender_scope.
     * Ordered by priority desc, then newest first.
     *
     * @return Collection<int, Banner>
     */
    public function activeForMarketerList(string $placementCode, ?string $gender, ?string $countryId): Collection
    {
        return Banner::with(['files', 'marketers.marketerProfile:id,marketer_id,profile_slug'])
            ->where('placement_code', $placementCode)
            ->live($countryId)
            ->forMarketerList($gender)
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Live 'marketer_profile_slider' banners for one marketer's details page: matching global
     * banners plus banners explicitly selected for this marketer. Ordered by priority desc.
     *
     * @return Collection<int, Banner>
     */
    public function activeForMarketerProfile(Marketer $marketer, ?string $countryId): Collection
    {
        return Banner::with(['files', 'marketers.marketerProfile:id,marketer_id,profile_slug'])
            ->where('placement_code', 'marketer_profile_slider')
            ->live($countryId)
            ->forMarketerProfile($marketer)
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Delete an image for a banner by slot (desktop / mobile).
     */
    public function deleteImageBySlot(Banner $banner, string $slot): void
    {
        $fileType = 'banner_'.$this->normalizeSlot($slot);

        $banner->files()
            ->where('file_type', $fileType)
            ->each(function (File $f) {
                $f->delete(); // File::booted deletes physical file
            });
    }

    /**
     * Delete all images attached to a banner.
     */
    public function deleteAllImages(Banner $banner): void
    {
        $banner->files()->each(fn (File $f) => $f->delete());
    }

    /**
     * Delete a specific File record by ID (verifies it belongs to a banner).
     */
    public function deleteFileRecord(int $fileId): bool
    {
        $file = File::where('id', $fileId)
            ->where('model_type', Banner::class)
            ->first();

        if (! $file) {
            return false;
        }

        $file->delete();

        return true;
    }

    /**
     * Duplicate a banner record (without duplicating images).
     */
    public function duplicate(Banner $source, string $adminId): Banner
    {
        $copy = $source->replicate(['impressions_count', 'clicks_count']);
        $copy->name = 'Copy of '.$source->name;
        $copy->status = 'inactive';
        $copy->created_by_admin_id = $adminId;
        $copy->updated_by_admin_id = null;
        $copy->impressions_count = 0;
        $copy->clicks_count = 0;
        $copy->save();

        // Keep marketer targeting (selected marketers) on the copy.
        $marketerIds = $source->marketers()->pluck('marketers.id')->all();
        if ($marketerIds !== []) {
            $copy->marketers()->sync($marketerIds);
        }

        return $copy;
    }
}
