<?php

namespace App\DTOs;

readonly class UnifiedBookingDTO
{
    public function __construct(
        public string $id,
        public string $type,           // 'travel_package' | 'bookable_unit' | 'flight'
        public string $bookingNumber,
        public string $title,
        public string $dateFrom,
        public string $dateTo,
        public int $totalPrice,
        public string $currency,
        public string $status,
        public ?string $agencyName,
        public ?string $thumbnailUrl,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'booking_number' => $this->bookingNumber,
            'title' => $this->title,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'total_price' => $this->totalPrice,
            'currency' => $this->currency,
            'status' => $this->status,
            'agency_name' => $this->agencyName,
            'thumbnail_url' => $this->thumbnailUrl,
        ];
    }
}
