<?php

namespace App\Models;

use App\Enums\FlashSalePriceHistoryRecordedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSalePriceHistory extends Model
{
    use HasUuids;

    protected $fillable = [
        'vendor_listing_id',
        'admin_listing_id',
        'price',
        'currency',
        'recorded_at',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'recorded_at' => 'datetime',
            'recorded_by' => FlashSalePriceHistoryRecordedBy::class,
        ];
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class, 'vendor_listing_id');
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class, 'admin_listing_id');
    }
}
