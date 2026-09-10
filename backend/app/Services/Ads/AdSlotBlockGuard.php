<?php

namespace App\Services\Ads;

use App\Enums\PaidAdBookingStatus;
use App\Models\AdImageItem;
use App\Models\Admin;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PaidAdBooking;
use App\Models\PaidAdSlot;
use App\Models\SliderSlide;
use App\Notifications\Ads\AdSlotContentChangedNotification;
use Illuminate\Support\Collection;

/**
 * Guards page-builder actions (hide block, delete block/slide/ad-image,
 * reorder, archive page) against silently breaking a live/paid ad booking.
 *
 * All state transitions on bookings go through AdBookingService. Money never
 * needs /100 or *100 here — everything stays BIGINT base-currency units.
 */
class AdSlotBlockGuard
{
    public function __construct(
        private AdBookingService $bookingService,
        private AdBillingService $billingService,
        private PaidAdResolver $resolver,
    ) {
    }

    public function slotsOnBlock(PageBlock $block): Collection
    {
        return PaidAdSlot::where('page_block_id', $block->id)->whereNull('deleted_at')->get();
    }

    public function activeBookingsOnBlock(PageBlock $block): Collection
    {
        $slotIds = $this->slotsOnBlock($block)->pluck('id');
        if ($slotIds->isEmpty()) {
            return collect();
        }

        return PaidAdBooking::whereIn('paid_ad_slot_id', $slotIds)
            ->whereIn('status', PaidAdBookingStatus::holdingStatuses())
            ->with(['slot', 'vendor', 'marketer'])
            ->get();
    }

    public function slideVisualRank(SliderSlide $slide): ?int
    {
        $ranked = SliderSlide::where('page_block_id', $slide->page_block_id)
            ->where('is_active', true)
            ->orderBy('position')
            ->pluck('id')
            ->values();

        $idx = $ranked->search($slide->id);

        return $idx !== false ? $idx + 1 : null;
    }

    public function adImageVisualRank(AdImageItem $item): ?int
    {
        $ranked = AdImageItem::where('page_block_id', $item->page_block_id)
            ->where('is_active', true)
            ->orderBy('position')
            ->pluck('id')
            ->values();

        $idx = $ranked->search($item->id);

        return $idx !== false ? $idx + 1 : null;
    }

    public function slotsAtPosition(string $blockId, int $itemPosition): Collection
    {
        return PaidAdSlot::where('page_block_id', $blockId)
            ->where('item_position', $itemPosition)
            ->whereNull('deleted_at')
            ->get();
    }

    public function activeBookingsAtPosition(string $blockId, int $itemPosition): Collection
    {
        $slotIds = $this->slotsAtPosition($blockId, $itemPosition)->pluck('id');
        if ($slotIds->isEmpty()) {
            return collect();
        }

        return PaidAdBooking::whereIn('paid_ad_slot_id', $slotIds)
            ->whereIn('status', PaidAdBookingStatus::holdingStatuses())
            ->with(['slot', 'vendor', 'marketer'])
            ->get();
    }

    public function activeBookingsOnPage(Page $page): Collection
    {
        $blockIds = $page->blocks()->pluck('id');
        if ($blockIds->isEmpty()) {
            return collect();
        }

        $slotIds = PaidAdSlot::whereIn('page_block_id', $blockIds)->whereNull('deleted_at')->pluck('id');
        if ($slotIds->isEmpty()) {
            return collect();
        }

        return PaidAdBooking::whereIn('paid_ad_slot_id', $slotIds)
            ->whereIn('status', PaidAdBookingStatus::holdingStatuses())
            ->with(['slot.pageBlock', 'vendor', 'marketer'])
            ->get();
    }

    /**
     * Block toggled hidden (not deleted). Bookings stay alive but the
     * advertiser is notified their ad has stopped rendering, and the
     * resolver cache is busted so the slot stops being served.
     */
    public function onBlockHidden(PageBlock $block, string $reason = 'block_hidden'): void
    {
        $bookings = $this->activeBookingsOnBlock($block);
        if ($bookings->isEmpty()) {
            return;
        }

        $this->resolver->bust($block->page->country_id ?? '');

        foreach ($bookings as $booking) {
            $this->notifyAdvertiser($booking, 'block_hidden', [
                'reason' => $reason,
                'block_type' => $block->block_type,
                'slot_code' => $booking->slot->slot_code,
            ]);
        }
    }

    /**
     * Block permanently removed. Every active booking on it is cancelled
     * (with proration handled inside AdBookingService::cancel -> refund),
     * and every slot on the block is soft-deleted so it can't be re-booked.
     */
    public function onBlockDeleted(PageBlock $block, string $adminId): void
    {
        $admin = Admin::find($adminId);

        $bookings = $this->activeBookingsOnBlock($block);
        foreach ($bookings as $booking) {
            $this->bookingService->cancel(
                $booking,
                'admin',
                'The page block hosting this ad slot was deleted.',
                $admin
            );
        }

        PaidAdSlot::where('page_block_id', $block->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'is_available' => false]);

        $this->resolver->bust($block->page->country_id ?? '');
    }

    /**
     * A single slide/ad-image at a given visual position was deleted.
     * Cancel any bookings bound to that position and retire the slot(s).
     */
    public function onItemDeleted(PageBlock $block, int $itemPosition, string $adminId): void
    {
        $admin = Admin::find($adminId);

        $bookings = $this->activeBookingsAtPosition($block->id, $itemPosition);
        foreach ($bookings as $booking) {
            $this->bookingService->cancel(
                $booking,
                'admin',
                "Position #{$itemPosition} in the ad block was deleted.",
                $admin
            );
        }

        PaidAdSlot::where('page_block_id', $block->id)
            ->where('item_position', $itemPosition)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'is_available' => false]);

        $this->resolver->bust($block->page->country_id ?? '');
    }

    /**
     * Slides/ad-images inside a block were reordered. Any slot bound to a
     * specific item (via bound_item_id) gets its item_position remapped to
     * follow the item, and active bookings on a slot whose rank changed are
     * notified (their position in the carousel/grid moved).
     *
     * @param array $rankedIds DB ids of the items, in their NEW visual order (0-based array)
     */
    public function onItemsReordered(PageBlock $block, array $rankedIds): void
    {
        $slots = PaidAdSlot::where('page_block_id', $block->id)
            ->whereNotNull('item_position')
            ->whereNull('deleted_at')
            ->get();

        if ($slots->isEmpty()) {
            return;
        }

        $rankMap = collect($rankedIds)->values()->mapWithKeys(fn ($dbId, $idx) => [$dbId => $idx + 1]);
        $changed = false;

        foreach ($slots as $slot) {
            if (! $slot->bound_item_id || ! isset($rankMap[$slot->bound_item_id])) {
                continue;
            }

            $newRank = $rankMap[$slot->bound_item_id];
            $oldRank = $slot->item_position;

            if ($newRank === $oldRank) {
                continue;
            }

            $slot->update(['item_position' => $newRank]);
            $changed = true;

            $bookings = PaidAdBooking::where('paid_ad_slot_id', $slot->id)
                ->whereIn('status', PaidAdBookingStatus::holdingStatuses())
                ->get();

            foreach ($bookings as $booking) {
                $this->notifyAdvertiser($booking, 'position_shifted', [
                    'old_rank' => $oldRank,
                    'new_rank' => $newRank,
                    'slot_code' => $slot->slot_code,
                ]);
            }
        }

        if ($changed) {
            $this->resolver->bust($block->page->country_id ?? '');
        }
    }

    /**
     * Page archived (e.g. via unpublish/scheduler). Bookings are left alone
     * (they still exist and will resume if the page is republished) but the
     * resolver cache is busted so they stop being served, and advertisers
     * are notified their ad went dark.
     */
    public function onPageArchived(Page $page): void
    {
        $bookings = $this->activeBookingsOnPage($page);
        if ($bookings->isEmpty()) {
            return;
        }

        $this->resolver->bust($page->country_id);

        foreach ($bookings as $booking) {
            $this->notifyAdvertiser($booking, 'page_archived', [
                'page_name' => $page->name,
                'slot_code' => $booking->slot->slot_code,
            ]);
        }
    }

    private function notifyAdvertiser(PaidAdBooking $booking, string $eventType, array $extra): void
    {
        $notification = new AdSlotContentChangedNotification($booking, $eventType, $extra);

        if ($booking->advertiser_type->value === 'vendor' && $booking->vendor) {
            $booking->vendor->vendorAdmins->each->notify($notification);
        } elseif ($booking->advertiser_type->value === 'marketer' && $booking->marketer) {
            $booking->marketer->marketerAdmins->each->notify($notification);
        }
    }
}
