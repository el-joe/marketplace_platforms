<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use App\Enums\MarketerCommissionDiscountType;
use App\Enums\PromotionCategory;
use App\Services\Customer\MarketerProfileCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MarketerProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_id',
        'banner_file_id',
        'avatar_file_id',
        'video_url',
        'bio_ar',
        'bio_en',
        'specialty_ar',
        'specialty_en',
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
        'story_price',
        'post_price',
        'video_price',
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
        'promotion_category',
        'monthly_promotion_target',
        'commission_discount_type',
        'commission_discount_flat',
        'commission_discount_percentage',
        'commission_discount_notes',
        'platform_cut_percentage',
    ];

    protected $casts = [
        'social_links' => 'array',
        'contact_details' => 'array',
        'ad_price' => DecimalMoney::class,
        'can_self_edit_ad_price' => 'boolean',
        'story_price' => DecimalMoney::class,
        'post_price' => DecimalMoney::class,
        'video_price' => DecimalMoney::class,
        'chest_cm' => 'float',
        'waist_cm' => 'float',
        'hip_cm' => 'float',
        'height_cm' => 'float',
        'item_length_cm' => 'float',
        'sleeve_from_neck_cm' => 'float',
        'sleeve_from_shoulder_cm' => 'float',
        'sleeve_width_cm' => 'float',
        'broker_serves_all_cities' => 'boolean',
        'promotion_category' => PromotionCategory::class,
        'monthly_promotion_target' => 'integer',
        'commission_discount_type' => MarketerCommissionDiscountType::class,
        'commission_discount_flat' => DecimalMoney::class,
        'commission_discount_percentage' => 'float',
        'platform_cut_percentage' => 'decimal:2',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class, 'marketer_id');
    }

    public function bannerFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'banner_file_id');
    }

    public function avatarFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'avatar_file_id');
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
     * LEGACY: no longer used on marketer commissions. Since P11 the discount
     * discounts the admin CUT (see MarketerCommissionStatementService), it
     * never lowers the gross commission.
     *
     * Apply this marketer's admin-granted commission discount to a gross
     * commission amount (platform base currency units, BIGINT). Never
     * discounts below zero.
     */
    public function applyCommissionDiscount(int|float $grossCommission): float
    {
        $discount = match ($this->commission_discount_type) {
            MarketerCommissionDiscountType::Flat => min((float) $this->commission_discount_flat, $grossCommission),
            MarketerCommissionDiscountType::Percentage => round($grossCommission * $this->commission_discount_percentage / 100, 2),
            default => 0,
        };

        return max(0.0, round($grossCommission - $discount, 2));
    }

    protected static function booted(): void
    {
        static::creating(function (self $profile) {
            if (empty($profile->profile_slug)) {
                $marketer = $profile->marketer ?? Marketer::find($profile->marketer_id);
                $base = Str::slug($marketer?->name ?? 'marketer');
                $profile->profile_slug = $base.'-'.Str::lower(Str::random(6));
            }
        });

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

    /**
     * Active affiliate brokers that should receive the given request.
     */
    public function scopeMatchingRequest(Builder $q, CustomerSpecialRequest $r): Builder
    {
        return $q->whereHas('marketer', fn ($m) => $m->whereHas('marketerJobs', fn ($j) => $j->where('key', 'affiliate'))->where('global_status', 'active'))
            ->where('broker_category_id', $r->category_id)
            ->when($r->city_id, fn ($w) => $w->where(function ($c) use ($r) {
                $c->where('broker_serves_all_cities', true)->orWhere('broker_city_id', $r->city_id);
            }));
    }
}
