<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleAnalytic extends Model
{
    use HasUuids;

    protected $table = 'flash_sale_analytics';

    protected $fillable = [
        'flash_sale_id',
        'flash_sale_submission_id',
        'vendor_id',
        'date',
        'units_sold',
        'currency',
        'gross_revenue',
        'revenue_at_normal_price',
        'discount_given',
        'platform_commission',
        'vendor_payout',
        'views',
        'add_to_cart_count',
        'conversion_rate',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'gross_revenue' => 'integer',
            'revenue_at_normal_price' => 'integer',
            'discount_given' => 'integer',
            'platform_commission' => 'integer',
            'vendor_payout' => 'integer',
            'conversion_rate' => 'decimal:4',
        ];
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function flashSaleSubmission(): BelongsTo
    {
        return $this->belongsTo(FlashSaleSubmission::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
