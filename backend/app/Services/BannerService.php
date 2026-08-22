<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BannerService
{
    private const DISK = 'public';

    /**
     * Store a banner image (desktop or mobile) and create a files record.
     *
     * @param  UploadedFile  $file
     * @param  Banner        $banner
     * @param  string        $slot   'desktop' | 'mobile'
     * @return File
     */
    public function storeImage(UploadedFile $file, Banner $banner, string $slot = 'desktop'): File
    {
        // Remove existing image for this slot
        $this->deleteImageBySlot($banner, $slot);

        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension();
        $key = 'banners/' . $banner->id . '/' . $slot . '_' . Str::random(8) . '.' . $ext;
        $path = $file->storeAs('banners/' . $banner->id, $slot . '_' . Str::random(8) . '.' . $ext, self::DISK);

        return File::create([
            'key' => $key,
            'path' => $path,
            'storage_type' => self::DISK,
            'file_type' => $slot === 'mobile' ? 'banner_mobile' : 'banner_desktop',
            'mime_type' => $file->getMimeType(),
            'extension' => $ext,
            'size' => $file->getSize(),
            'model_type' => Banner::class,
            'model_id' => $banner->id,
            'is_primary' => $slot === 'desktop' ? 1 : 0,
            'position' => $slot === 'desktop' ? 0 : 1,
        ]);
    }

    /**
     * Get the desktop image for a banner.
     */
    public function getDesktopImage(Banner $banner): ?File
    {
        return $banner->files()
            ->where('file_type', 'banner_desktop')
            ->first();
    }

    /**
     * Get the mobile image for a banner.
     */
    public function getMobileImage(Banner $banner): ?File
    {
        return $banner->files()
            ->where('file_type', 'banner_mobile')
            ->first();
    }

    /**
     * Resolve the active banner for a customer-facing placement (e.g. 'cart_banner'),
     * respecting country, audience and the active date window. Ties broken by priority.
     */
    public function getActivePlacement(string $placementCode, ?string $countryId, string $audience = 'guest'): ?Banner
    {
        $now = now();

        return Banner::with('files')
            ->where('placement_code', $placementCode)
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->where(fn ($q) => $q->whereNull('country_id')->orWhere('country_id', $countryId))
            ->whereIn('audience', ['all', $audience])
            ->orderByDesc('priority')
            ->first();
    }

    /**
     * Delete an image for a banner by slot (desktop / mobile).
     */
    public function deleteImageBySlot(Banner $banner, string $slot): void
    {
        $fileType = $slot === 'mobile' ? 'banner_mobile' : 'banner_desktop';

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
        $banner->files()->each(fn(File $f) => $f->delete());
    }

    /**
     * Delete a specific File record by ID (verifies it belongs to a banner).
     */
    public function deleteFileRecord(int $fileId): bool
    {
        $file = File::where('id', $fileId)
            ->where('model_type', Banner::class)
            ->first();

        if (!$file) {
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
        $copy->name = 'Copy of ' . $source->name;
        $copy->status = 'inactive';
        $copy->created_by_admin_id = $adminId;
        $copy->updated_by_admin_id = null;
        $copy->impressions_count = 0;
        $copy->clicks_count = 0;
        $copy->save();

        return $copy;
    }
}
