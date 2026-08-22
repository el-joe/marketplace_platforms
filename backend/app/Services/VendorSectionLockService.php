<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\VendorSectionLock;
use Illuminate\Support\Facades\Cache;

class VendorSectionLockService
{
    public function __construct(private readonly ActivityLoggerService $activityLogger) {}

    public function isLocked(string $vendorId, string $section): bool
    {
        if ($section === VendorSectionLock::SECTION_BANK_ACCOUNTS) {
            return true;
        }

        return Cache::remember(
            "vendor_lock_{$vendorId}_{$section}",
            60,
            fn () => VendorSectionLock::query()
                ->where('vendor_id', $vendorId)
                ->where('section', $section)
                ->where('is_locked', true)
                ->exists()
        );
    }

    public function lockSection(string $vendorId, string $section, string $reason, Admin $admin): VendorSectionLock
    {
        $lock = VendorSectionLock::query()->updateOrCreate(
            ['vendor_id' => $vendorId, 'section' => $section],
            [
                'is_locked' => true,
                'locked_reason' => $reason,
                'locked_by_admin_id' => $admin->id,
                'locked_at' => now(),
                'unlocked_by_admin_id' => null,
                'unlocked_at' => null,
            ]
        );

        Cache::forget("vendor_lock_{$vendorId}_{$section}");

        $this->activityLogger->log(
            description: "Section locked: {$section}",
            subject: $lock->vendor,
            causer: $admin,
            properties: ['section' => $section, 'reason' => $reason],
            logName: 'vendor_profile',
            event: 'section_locked',
        );

        return $lock;
    }

    public function unlockSection(string $vendorId, string $section, Admin $admin): void
    {
        $lock = VendorSectionLock::query()->updateOrCreate(
            ['vendor_id' => $vendorId, 'section' => $section],
            [
                'is_locked' => false,
                'unlocked_by_admin_id' => $admin->id,
                'unlocked_at' => now(),
            ]
        );

        Cache::forget("vendor_lock_{$vendorId}_{$section}");

        $this->activityLogger->log(
            description: "Section unlocked: {$section}",
            subject: $lock->vendor,
            causer: $admin,
            properties: ['section' => $section],
            logName: 'vendor_profile',
            event: 'section_unlocked',
        );
    }

    public function getLockStatus(string $vendorId): array
    {
        return collect(VendorSectionLock::sections())
            ->mapWithKeys(fn (string $section) => [$section => $this->isLocked($vendorId, $section)])
            ->all();
    }
}
