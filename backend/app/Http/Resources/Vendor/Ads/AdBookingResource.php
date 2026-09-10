<?php

namespace App\Http\Resources\Vendor\Ads;

use App\Enums\PaidAdAdvertiserType;
use App\Enums\PaidAdBookingStatus;
use App\Enums\PaidAdCreativeStatus;
use App\Services\Ads\AdAttributionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentCreative = $this->currentCreative ?? $this->creatives->firstWhere('is_current', true);
        $pendingCreative = $this->creatives
            ? $this->creatives->firstWhere('status', PaidAdCreativeStatus::PendingReview)
            : null;

        return [
            'id' => $this->id,
            'reference' => $this->booking_reference,
            'status' => [
                'value' => $this->status?->value,
                'label' => __('ads.booking_status.'.$this->status?->value),
            ],
            'payment_status' => [
                'value' => $this->payment_status?->value,
                'label' => __('ads.payment_status.'.$this->payment_status?->value),
            ],
            'payment_method' => $this->payment_method?->value,
            'slot' => new AdSlotResource($this->whenLoaded('slot')),
            'booked_from' => $this->booked_from?->toDateString(),
            'booked_until' => $this->booked_until?->toDateString(),
            'pricing_units' => $this->pricing_units,
            'unit_rate' => $this->unit_rate,
            'quoted_amount' => $this->quoted_amount,
            'tax_amount' => $this->tax_amount,
            'budget_amount' => $this->budget_amount,
            'total_charged' => $this->total_charged,
            'currency' => $this->currency,
            'payment_due_at' => $this->payment_due_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'current_creative' => $currentCreative ? new CreativeResource($currentCreative) : null,
            'pending_creative' => $pendingCreative ? new CreativeResource($pendingCreative) : null,
            'attribution' => $this->when(
                $this->advertiser_type === PaidAdAdvertiserType::Marketer,
                fn () => app(AdAttributionService::class)->forBooking($this->resource)
            ),
            'timeline' => $this->timeline(),
            'can' => [
                'submit' => $this->status === PaidAdBookingStatus::Draft && $currentCreative !== null,
                'pay' => $this->status === PaidAdBookingStatus::Approved && $this->payment_status?->value === 'unpaid',
                'cancel' => in_array($this->status, [
                    PaidAdBookingStatus::Draft,
                    PaidAdBookingStatus::PendingReview,
                    PaidAdBookingStatus::Approved,
                    PaidAdBookingStatus::Scheduled,
                ], true),
                'replace_creative' => in_array($this->status, [
                    PaidAdBookingStatus::Draft,
                    PaidAdBookingStatus::PendingReview,
                    PaidAdBookingStatus::Approved,
                    PaidAdBookingStatus::Scheduled,
                    PaidAdBookingStatus::Active,
                    PaidAdBookingStatus::Paused,
                ], true),
            ],
        ];
    }

    private function timeline(): array
    {
        $events = [
            ['at' => $this->created_at, 'event' => 'created'],
            ['at' => $this->submitted_at, 'event' => 'submitted'],
            ['at' => $this->approved_at, 'event' => 'approved'],
            ['at' => $this->rejected_at, 'event' => 'rejected'],
            ['at' => $this->paid_at, 'event' => 'paid'],
            ['at' => $this->started_at, 'event' => 'started'],
            ['at' => $this->completed_at, 'event' => 'completed'],
            ['at' => $this->cancelled_at, 'event' => 'cancelled'],
        ];

        return collect($events)
            ->filter(fn ($e) => $e['at'] !== null)
            ->sortBy('at')
            ->map(fn ($e) => ['event' => $e['event'], 'at' => $e['at']->toISOString()])
            ->values()
            ->all();
    }
}
