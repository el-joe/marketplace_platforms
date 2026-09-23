<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FlightBooking extends Model
{
    use HasUuids;

    protected $fillable = [
        'booking_number', 'customer_id', 'travel_agency_id', 'airline_name', 'flight_number',
        'origin_city', 'destination_city', 'departure_at', 'arrival_at', 'passengers_count',
        'total_price', 'currency', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'departure_at' => 'datetime',
            'arrival_at' => 'datetime',
            'passengers_count' => 'integer',
            'total_price' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $b) => $b->booking_number ??= 'FLT-'.strtoupper(Str::random(8)));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function travelAgency(): BelongsTo
    {
        return $this->belongsTo(TravelAgency::class);
    }
}
