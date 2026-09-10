<?php

namespace App\Notifications\Ads;

use App\Enums\PaidAdAdvertiserType;
use App\Models\PaidAdBooking;
use App\Notifications\BaseDatabaseBroadcastNotification;

/**
 * Sent to the advertiser (vendor or marketer admins) when a page-builder
 * action changes what their active ad booking is doing without cancelling
 * it outright: the hosting block was hidden, the page was archived, or the
 * ad's visual position shifted after a reorder.
 */
class AdSlotContentChangedNotification extends BaseDatabaseBroadcastNotification
{
    /**
     * @param string $eventType one of: block_hidden, position_shifted, page_archived
     * @param array $extra event-specific placeholders (slot_code, old_rank, new_rank, page_name, reason, block_type)
     */
    public function __construct(
        private readonly PaidAdBooking $booking,
        private readonly string $eventType,
        private readonly array $extra = [],
    ) {
    }

    public function notificationType(): string
    {
        return 'ad_slot_' . $this->eventType;
    }

    public function notificationData(object $notifiable): array
    {
        $replace = array_merge($this->extra, ['booking_reference' => $this->booking->booking_reference]);

        return [
            'title' => __('notifications.ads.' . $this->eventType . '.title', $replace),
            'message' => __('notifications.ads.' . $this->eventType . '.message', $replace),
            'url' => $this->url(),
            'booking_id' => $this->booking->id,
            'event_type' => $this->eventType,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }

    private function url(): ?string
    {
        if ($this->booking->advertiser_type === PaidAdAdvertiserType::Vendor) {
            return route('partner.ad-bookings.show', $this->booking->id);
        }

        if ($this->booking->advertiser_type === PaidAdAdvertiserType::Marketer) {
            return route('marketer.promote.bookings.show', $this->booking->id);
        }

        return null;
    }
}
