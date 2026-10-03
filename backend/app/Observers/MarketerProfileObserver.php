<?php

namespace App\Observers;

use App\Jobs\GenerateEntityQrCodeJob;
use App\Models\MarketerProfile;

class MarketerProfileObserver
{
    public function created(MarketerProfile $profile): void
    {
        $this->dispatchQrJob($profile);
    }

    public function updated(MarketerProfile $profile): void
    {
        if ($profile->wasChanged(['profile_slug'])) {
            $this->dispatchQrJob($profile);
        }
    }

    private function dispatchQrJob(MarketerProfile $profile): void
    {
        if (! $profile->profile_slug) {
            return;
        }

        dispatch(new GenerateEntityQrCodeJob(
            model: $profile,
            scanUrl: route('qr.scan.marketer', ['slug' => $profile->profile_slug]),
            label: $profile->marketer?->name ?? $profile->profile_slug,
            storagePath: "qr/marketers/{$profile->profile_slug}.png",
        ));
    }
}
