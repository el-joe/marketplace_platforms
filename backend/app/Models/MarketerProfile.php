<?php

namespace App\Models;

use App\Enums\MarketerCommissionDiscountType;
use App\Services\Customer\MarketerProfileCache;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_id',
        'banner_file_id',
        'video_url',
        'bio_ar',
        'bio_en',
        'social_links',
        'contact_details',
        'qr_code_path',
        'profile_slug',
        'total_campaigns',
        'total_conversions',
        'total_earnings',
        'earnings_currency',
        'ad_price',
        'ad_price_currency',
        'can_self_edit_ad_price',
        'clothing_size',
        'shirt_size',
        'pants_size',
        'dress_size',
        'abaya_size',
        'shoe_size',
        'shoe_size_system',
        'chest_cm',
        'waist_cm',
        'hip_cm',
        'height_cm',
        'item_length_cm',
        'sleeve_from_neck_cm',
        'sleeve_from_shoulder_cm',
        'sleeve_width_cm',
        'measurements_notes',
        'broker_category_id',
        'broker_city_id',
        'broker_serves_all_cities',
        'commission_discount_type',
        'commission_discount_flat',
        'commission_discount_percentage',
        'commission_discount_notes',
    ];

    protected $casts = [
        'social_links'             => 'array',
        'contact_details'          => 'array',
        'ad_price'                 => 'integer',
        'can_self_edit_ad_price'   => 'boolean',
        'chest_cm'                 => 'float',
        'waist_cm'                 => 'float',
        'hip_cm'                   => 'float',
        'height_cm'                => 'float',
        'item_length_cm'           => 'float',
        'sleeve_from_neck_cm'      => 'float',
        'sleeve_from_shoulder_cm'  => 'float',
        'sleeve_width_cm'          => 'float',
        'broker_serves_all_cities' => 'boolean',
        'commission_discount_type' => MarketerCommissionDiscountType::class,
        'commission_discount_flat' => 'integer',
        'commission_discount_percentage' => 'float',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class, 'marketer_id');
    }

    public function bannerFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'banner_file_id');
    }

    public function brokerCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'broker_category_id');
    }

    public function brokerCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'broker_city_id');
    }

    /**
     * Apply this marketer's admin-granted commission discount to a gross
     * commission amount (platform base currency units, BIGINT). Never
     * discounts below zero.
     */
    public function applyCommissionDiscount(int $grossCommission): int
    {
        $discount = match ($this->commission_discount_type) {
            MarketerCommissionDiscountType::Flat => min($this->commission_discount_flat, $grossCommission),
            MarketerCommissionDiscountType::Percentage => (int) floor($grossCommission * $this->commission_discount_percentage / 100),
            default => 0,
        };

        return max(0, $grossCommission - $discount);
    }

    protected static function booted(): void
    {
        static::saved(function (self $profile) {
            MarketerProfileCache::bump($profile->profile_slug);

            if ($profile->wasChanged('profile_slug') && $profile->getOriginal('profile_slug')) {
                MarketerProfileCache::bump($profile->getOriginal('profile_slug'));
            }
        });

        static::deleted(function (self $profile) {
            MarketerProfileCache::bump($profile->profile_slug);
        });
    }
}
