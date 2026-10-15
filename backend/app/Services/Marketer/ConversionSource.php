<?php

namespace App\Services\Marketer;

use App\Models\MarketerCampaignConversion;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a conversion was earned on. One source = at most one conversion, ever (the recorder is idempotent per source).
 *
 *   orderItem(...)       product sale, one per order item
 *   classifiedSale(...)  a classified listing sold through a referral (one per listing)
 *   travelBooking(...)   a travel booking made through a referral (one per booking)
 *   classifiedInquiry()  a lead; legacy flow, recorded but never auto-approved
 */
final class ConversionSource
{
    private function __construct(
        public readonly string $type,
        public readonly array $keys,
        public readonly array $columns,
    ) {}

    public static function orderItem(string $orderId, string $orderItemId): self
    {
        return new self(
            MarketerCampaignConversion::TYPE_PRODUCT_SALE,
            ['order_item_id' => $orderItemId],
            ['order_id' => $orderId, 'order_item_id' => $orderItemId],
        );
    }

    public static function classifiedSale(string $classifiedListingId): self
    {
        return new self(
            MarketerCampaignConversion::TYPE_CLASSIFIED_SALE,
            ['classified_listing_id' => $classifiedListingId, 'conversion_type' => MarketerCampaignConversion::TYPE_CLASSIFIED_SALE],
            ['classified_listing_id' => $classifiedListingId],
        );
    }

    public static function classifiedInquiry(string $classifiedInquiryId, ?string $classifiedListingId = null): self
    {
        return new self(
            MarketerCampaignConversion::TYPE_CLASSIFIED_INQUIRY,
            ['classified_inquiry_id' => $classifiedInquiryId],
            ['classified_inquiry_id' => $classifiedInquiryId, 'classified_listing_id' => $classifiedListingId],
        );
    }

    public static function travelBooking(string $travelBookingId): self
    {
        return new self(
            MarketerCampaignConversion::TYPE_TRAVEL_BOOKING,
            ['travel_booking_id' => $travelBookingId],
            ['travel_booking_id' => $travelBookingId],
        );
    }

    /** The conversion already recorded for this source, if any. */
    public function existing(): ?MarketerCampaignConversion
    {
        return $this->query()->first();
    }

    public function query(): Builder
    {
        $query = MarketerCampaignConversion::query();
        foreach ($this->keys as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }
}
