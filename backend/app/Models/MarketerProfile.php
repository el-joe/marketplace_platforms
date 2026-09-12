<?php

namespace App\Models;

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
        'height_cm',
        'measurements_notes',
    ];

    protected $casts = [
        'social_links'           => 'array',
        'contact_details'        => 'array',
        'ad_price'               => 'integer',
        'can_self_edit_ad_price' => 'boolean',
        'chest_cm'               => 'float',
        'waist_cm'               => 'float',
        'height_cm'              => 'float',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class, 'marketer_id');
    }

    public function bannerFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'banner_file_id');
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
