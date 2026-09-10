<?php

namespace App\Services\Ads;

use App\Enums\PaidAdBookingStatus;
use App\Enums\PaidAdCreativeStatus;
use App\Models\Admin;
use App\Models\File;
use App\Models\PaidAdBooking;
use App\Models\PaidAdCreative;
use App\Notifications\Ads\AdCreativeRejectedNotification;
use DomainException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdCreativeService
{
    private const DISK = 'public';

    private const REQUIRED_SLOTS = ['desktop_en', 'mobile_en'];

    private const OPTIONAL_SLOTS = ['desktop_ar', 'mobile_ar'];

    public function __construct(
        private readonly AdDestinationResolver $destinationResolver,
        private readonly PaidAdResolver $resolver,
    ) {
    }

    private const ALLOWED_UPLOAD_STATUSES = [
        PaidAdBookingStatus::Draft,
        PaidAdBookingStatus::PendingReview,
        PaidAdBookingStatus::Approved,
        PaidAdBookingStatus::Scheduled,
        PaidAdBookingStatus::Active,
        PaidAdBookingStatus::Paused,
    ];

    public function upload(PaidAdBooking $booking, array $data, array $files, Authenticatable $actor): PaidAdCreative
    {
        if (! in_array($booking->status, self::ALLOWED_UPLOAD_STATUSES, true)) {
            throw new DomainException(__('ads.errors.transition_not_allowed', [
                'from' => $booking->status->value,
                'to' => 'creative_upload',
            ]));
        }

        foreach (self::REQUIRED_SLOTS as $slot) {
            if (empty($files[$slot])) {
                throw new DomainException(__('ads.errors.creative_required'));
            }
        }

        $spec = $booking->slot->creativeSpec();

        foreach ([...self::REQUIRED_SLOTS, ...self::OPTIONAL_SLOTS] as $slot) {
            if (! empty($files[$slot])) {
                $this->validateImage($files[$slot], $slot, $spec);
            }
        }

        $destination = $this->destinationResolver->resolve(
            $booking,
            $data['destination_type'],
            $data['destination_reference_id'] ?? null,
            $data['external_url'] ?? null,
        );

        return DB::transaction(function () use ($booking, $files, $destination, $data, $actor) {
            $version = (int) $booking->creatives()->max('version') + 1;
            $hasCurrent = $booking->creatives()->where('is_current', true)->exists();

            $autoApprove = ! $booking->slot->requires_approval && ! $hasCurrent;

            $creative = PaidAdCreative::create([
                'paid_ad_booking_id' => $booking->id,
                'version' => $version,
                'vendor_id' => $booking->vendor_id,
                'marketer_id' => $booking->marketer_id,
                'title_en' => $data['title_en'] ?? null,
                'title_ar' => $data['title_ar'] ?? null,
                'subtitle_en' => $data['subtitle_en'] ?? null,
                'subtitle_ar' => $data['subtitle_ar'] ?? null,
                'cta_label_en' => $data['cta_label_en'] ?? null,
                'cta_label_ar' => $data['cta_label_ar'] ?? null,
                'destination_url' => $destination['destination_url'],
                'destination_type' => $destination['destination_type'],
                'destination_reference_id' => $destination['destination_reference_id'],
                'referral_code' => $destination['referral_code'],
                'status' => $autoApprove ? PaidAdCreativeStatus::Approved->value : PaidAdCreativeStatus::PendingReview->value,
                'is_current' => $autoApprove ? true : false,
                'approved_at' => $autoApprove ? now() : null,
            ]);

            foreach ([...self::REQUIRED_SLOTS, ...self::OPTIONAL_SLOTS] as $slot) {
                if (! empty($files[$slot])) {
                    $this->storeImage($files[$slot], $creative, $slot);
                }
            }

            if ($autoApprove) {
                $creative->update(['is_current' => true]);
                $this->resolver->bust($booking->country_id);
            }

            return $creative->fresh(['files']);
        });
    }

    public function approve(PaidAdCreative $c, Admin $admin): void
    {
        DB::transaction(function () use ($c, $admin) {
            $c = PaidAdCreative::whereKey($c->id)->lockForUpdate()->firstOrFail();

            PaidAdCreative::where('paid_ad_booking_id', $c->paid_ad_booking_id)
                ->where('id', '!=', $c->id)
                ->update(['is_current' => false]);

            $c->update([
                'status' => PaidAdCreativeStatus::Approved->value,
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'approved_at' => now(),
                'is_current' => true,
            ]);

            $this->resolver->bust($c->booking->country_id);
        });
    }

    public function reject(PaidAdCreative $c, Admin $admin, string $reason, ?string $code): void
    {
        DB::transaction(function () use ($c, $admin, $reason, $code) {
            $c = PaidAdCreative::whereKey($c->id)->lockForUpdate()->firstOrFail();

            $c->update([
                'status' => PaidAdCreativeStatus::Rejected->value,
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
                'rejection_code' => $code,
                'is_current' => false,
            ]);

            DB::afterCommit(fn () => AdBookingRecipients::advertiserAdmins($c->booking)->each(
                fn ($a) => $a->notify(new AdCreativeRejectedNotification($c, $reason, $code))
            ));
        });
    }

    private function validateImage(UploadedFile $file, string $slot, array $spec): void
    {
        $device = str_starts_with($slot, 'desktop') ? 'desktop' : 'mobile';
        $required = $spec[$device] ?? null;

        if ($required) {
            $size = @getimagesize($file->getRealPath());
            [$width, $height] = $size ?: [null, null];

            if ($width !== $required['w'] || $height !== $required['h']) {
                throw new DomainException(__('ads.errors.creative_dimensions', [
                    'slot' => $slot,
                    'required_width' => $required['w'],
                    'required_height' => $required['h'],
                    'actual_width' => $width ?? '?',
                    'actual_height' => $height ?? '?',
                ]));
            }
        }

        $maxKb = $spec['max_kb'] ?? null;
        if ($maxKb && ($file->getSize() / 1024) > $maxKb) {
            throw new DomainException(__('ads.errors.creative_size', ['slot' => $slot, 'max_kb' => $maxKb]));
        }

        $formats = $spec['formats'] ?? ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $formats, true)) {
            throw new DomainException(__('ads.errors.creative_format', ['slot' => $slot, 'formats' => implode(', ', $formats)]));
        }
    }

    private function storeImage(UploadedFile $file, PaidAdCreative $creative, string $slot): File
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $key = "{$slot}_".Str::random(8);
        $path = "ads/{$creative->paid_ad_booking_id}/v{$creative->version}/{$key}.{$ext}";

        $file->storeAs(dirname($path), basename($path), self::DISK);

        return File::create([
            'key' => $key,
            'path' => $path,
            'storage_type' => self::DISK,
            'file_type' => "ad_{$slot}",
            'mime_type' => $file->getMimeType(),
            'extension' => $ext,
            'size' => $file->getSize(),
            'model_type' => PaidAdCreative::class,
            'model_id' => $creative->id,
        ]);
    }
}
