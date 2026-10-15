<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketerCampaignConversion extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id', 'invitation_id', 'order_id', 'order_item_id',
        'referral_clicked_at', 'commission_amount', 'platform_commission_amount', 'currency',
        'commissioned', 'paid_at', 'sale_number_in_campaign',
        'status', 'approved_at', 'reversed_at', 'wallet_credited_at', 'wallet_released_at',
        'payout_id',
        'gross_commission_amount', 'platform_cut_percentage', 'platform_cut_base_amount', 'platform_cut_discount_amount',
        'platform_cut_amount', 'platform_cut_vat_rate', 'platform_cut_vat_amount', 'net_commission_amount',
        // v3: non-product verticals, basis and commission fee snapshot.
        'travel_booking_id', 'classified_inquiry_id', 'classified_listing_id', 'conversion_type',
        'sale_amount', 'commission_fee_amount', 'triggered_at', 'marketer_type', 'rule_source',
    ];

    public const TYPE_PRODUCT_SALE = 'product_sale';

    public const TYPE_TRAVEL_BOOKING = 'travel_booking';

    public const TYPE_CLASSIFIED_INQUIRY = 'classified_inquiry';

    public const TYPE_CLASSIFIED_SALE = 'classified_sale';

    protected $casts = [
        'referral_clicked_at' => 'datetime',
        'paid_at' => 'datetime',
        'commissioned' => 'boolean',
        'commission_amount' => DecimalMoney::class,
        'platform_commission_amount' => DecimalMoney::class,
        'gross_commission_amount' => DecimalMoney::class,
        'platform_cut_base_amount' => DecimalMoney::class,
        'platform_cut_discount_amount' => DecimalMoney::class,
        'platform_cut_amount' => DecimalMoney::class,
        'platform_cut_vat_amount' => DecimalMoney::class,
        'net_commission_amount' => DecimalMoney::class,
        'sale_amount' => DecimalMoney::class,
        'commission_fee_amount' => DecimalMoney::class,
        'triggered_at' => 'datetime',
        'approved_at' => 'datetime',
        'reversed_at' => 'datetime',
        'wallet_credited_at' => 'datetime',
        'wallet_released_at' => 'datetime',
    ];

    /** Gross commission this conversion is worth (the flash-sale bonus no longer exists). */
    public function getTotalCommissionAttribute(): float
    {
        return round((float) $this->commission_amount, 2);
    }

    /**
     * What this conversion was earned on, for display: the order number, the sold listing's title or the booking
     * number depending on the vertical.
     */
    public function reference(): string
    {
        return match ($this->conversion_type) {
            self::TYPE_TRAVEL_BOOKING => (string) ($this->travelBooking?->booking_number ?? $this->travel_booking_id),
            self::TYPE_CLASSIFIED_SALE, self::TYPE_CLASSIFIED_INQUIRY => (string) ($this->classifiedListing?->title_ar ?? $this->classifiedListing?->title_en ?? $this->classified_listing_id),
            default => (string) ($this->order?->order_number ?? $this->order_id),
        };
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignInvitation::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payoutItems(): HasMany
    {
        return $this->hasMany(PayoutItem::class, 'marketer_conversion_id');
    }

    public function travelBooking(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class, 'travel_booking_id');
    }

    public function classifiedListing(): BelongsTo
    {
        return $this->belongsTo(ClassifiedListing::class, 'classified_listing_id');
    }

    public function classifiedInquiry(): BelongsTo
    {
        return $this->belongsTo(ClassifiedInquiry::class, 'classified_inquiry_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
